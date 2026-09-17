<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Backup;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Application + database backup and restore.
 *
 * Backups are written to storage/backups as a ZIP of the application files
 * and a plain SQL dump. Both are checksummed and verified after creation —
 * an unverifiable backup is never accepted as a rollback point.
 */
final class BackupService
{
    /** Paths never included in a file backup. */
    private const EXCLUDED_DIRS = ['.git', 'storage/backups', 'storage/tmp', 'storage/cache', 'storage/sessions', 'node_modules', 'vendor/bin'];

    private string $directory;

    public function __construct()
    {
        $this->directory = STORAGE_PATH . '/backups';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
    }

    /**
     * Create a backup.
     *
     * @param string $type full|files|database
     * @return array<string,mixed> The backup row
     */
    public function create(string $type = 'full', string $source = 'manual', ?int $userId = null, bool $includeUploads = false): array
    {
        $backups = new Backup();
        $name = sprintf('backup-%s-%s', date('Ymd-His'), substr(bin2hex(random_bytes(3)), 0, 6));

        $id = $backups->create([
            'name'           => $name,
            'type'           => in_array($type, ['full', 'files', 'database'], true) ? $type : 'full',
            'trigger_source' => $source,
            'app_version'    => APP_VERSION,
            'status'         => 'running',
            'created_by'     => $userId,
            'created_at'     => now(),
        ]);

        try {
            @set_time_limit(600);
            $payload = ['status' => 'completed', 'completed_at' => now()];

            if ($type !== 'database') {
                $filesPath = $this->directory . '/' . $name . '-files.zip';
                $this->archiveFiles($filesPath, $includeUploads);
                $payload['files_path'] = 'backups/' . basename($filesPath);
                $payload['files_size'] = (int) filesize($filesPath);
                $payload['files_checksum'] = hash_file('sha256', $filesPath);
            }

            if ($type !== 'files') {
                $dbPath = $this->directory . '/' . $name . '-database.sql';
                $this->dumpDatabase($dbPath);
                $payload['database_path'] = 'backups/' . basename($dbPath);
                $payload['database_size'] = (int) filesize($dbPath);
                $payload['database_checksum'] = hash_file('sha256', $dbPath);
            }

            $backups->updateById($id, $payload);

            $row = (array) $backups->find($id);
            $verified = $this->verify($id);
            $row['verified'] = $verified['verified'] ? 1 : 0;

            if (!$verified['verified']) {
                $backups->updateById($id, ['status' => 'failed', 'error' => substr($verified['message'], 0, 400)]);
                $row['status'] = 'failed';
                $row['error'] = $verified['message'];
            }

            AuditLog::record('backup.created', 'backup', $id, ['type' => $type, 'source' => $source, 'verified' => $verified['verified']], $userId);
            $this->applyRetention();

            return $row;
        } catch (Throwable $e) {
            Logger::error('Backup failed: ' . $e->getMessage());
            $backups->updateById($id, ['status' => 'failed', 'error' => substr($e->getMessage(), 0, 400), 'completed_at' => now()]);

            return (array) $backups->find($id);
        }
    }

    /**
     * Verify a backup's integrity (file exists, checksum matches, ZIP opens,
     * SQL dump contains the end marker).
     *
     * @return array{verified:bool,message:string}
     */
    public function verify(int $backupId): array
    {
        $backups = new Backup();
        $backup = $backups->find($backupId);
        if ($backup === null) {
            return ['verified' => false, 'message' => 'Backup record not found.'];
        }

        $problems = [];

        if (!empty($backup['files_path'])) {
            $absolute = STORAGE_PATH . '/' . (string) $backup['files_path'];
            if (!is_file($absolute)) {
                $problems[] = 'File archive is missing.';
            } elseif (hash_file('sha256', $absolute) !== (string) $backup['files_checksum']) {
                $problems[] = 'File archive checksum mismatch.';
            } elseif (class_exists(ZipArchive::class)) {
                $zip = new ZipArchive();
                $opened = $zip->open($absolute, ZipArchive::CHECKCONS);
                if ($opened !== true) {
                    $problems[] = 'File archive is corrupt (code ' . $opened . ').';
                } else {
                    if ($zip->numFiles < 5) {
                        $problems[] = 'File archive looks empty.';
                    }
                    $zip->close();
                }
            }
        }

        if (!empty($backup['database_path'])) {
            $absolute = STORAGE_PATH . '/' . (string) $backup['database_path'];
            if (!is_file($absolute)) {
                $problems[] = 'Database dump is missing.';
            } elseif (hash_file('sha256', $absolute) !== (string) $backup['database_checksum']) {
                $problems[] = 'Database dump checksum mismatch.';
            } else {
                $tail = $this->tail($absolute, 200);
                if (!str_contains($tail, '-- DUMP COMPLETE')) {
                    $problems[] = 'Database dump is truncated.';
                }
            }
        }

        if ($backup['files_path'] === null && $backup['database_path'] === null) {
            $problems[] = 'Backup contains no data.';
        }

        $verified = $problems === [];
        $backups->updateById($backupId, ['verified' => $verified ? 1 : 0]);

        return ['verified' => $verified, 'message' => $verified ? 'Backup verified.' : implode(' ', $problems)];
    }

    /**
     * Restore a backup. Application files are replaced (respecting protected
     * paths) and/or the database is re-imported.
     *
     * @return array{success:bool,message:string}
     */
    public function restore(int $backupId, bool $files = true, bool $database = true, ?int $userId = null): array
    {
        $backup = (new Backup())->find($backupId);
        if ($backup === null) {
            return ['success' => false, 'message' => 'Backup not found.'];
        }

        $verification = $this->verify($backupId);
        if (!$verification['verified']) {
            return ['success' => false, 'message' => 'Refusing to restore an unverified backup: ' . $verification['message']];
        }

        try {
            @set_time_limit(900);

            if ($files && !empty($backup['files_path'])) {
                $this->restoreFiles(STORAGE_PATH . '/' . (string) $backup['files_path']);
            }

            if ($database && !empty($backup['database_path'])) {
                // The operational history must survive the restore: without
                // this the record of the failure, of the backup itself and
                // of the rollback would be rolled back along with the data.
                $history = $this->captureOperationalHistory();
                $this->importDatabase(STORAGE_PATH . '/' . (string) $backup['database_path']);
                $this->restoreOperationalHistory($history);
            }

            (new Backup())->updateById($backupId, ['status' => 'restored']);
            AuditLog::record('backup.restored', 'backup', $backupId, ['files' => $files, 'database' => $database], $userId);

            return ['success' => true, 'message' => 'Backup restored successfully.'];
        } catch (Throwable $e) {
            Logger::error('Restore failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Rows that describe the platform's own maintenance history. They are
     * re-applied after a database restore so an administrator can still see
     * what happened, and so existing backups are not forgotten.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function captureOperationalHistory(): array
    {
        $db = Database::instance();
        $history = [];

        foreach (['update_logs', 'backups'] as $table) {
            try {
                $history[$table] = $db->select('SELECT * FROM `' . $db->table($table) . '`');
            } catch (Throwable) {
                $history[$table] = [];
            }
        }

        return $history;
    }

    /** @param array<string,array<int,array<string,mixed>>> $history */
    private function restoreOperationalHistory(array $history): void
    {
        $db = Database::instance();

        foreach ($history as $table => $rows) {
            foreach ($rows as $row) {
                try {
                    $columns = array_keys($row);
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($column !== 'id') {
                            $updates[] = sprintf('`%s` = VALUES(`%s`)', $column, $column);
                        }
                    }
                    $sql = sprintf(
                        'INSERT INTO `%s` (%s) VALUES (%s)%s',
                        $db->table($table),
                        implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns)),
                        implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)),
                        $updates === [] ? '' : ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
                    );
                    $db->query($sql, $row);
                } catch (Throwable $e) {
                    Logger::warning('Could not preserve ' . $table . ' row across the restore: ' . $e->getMessage());
                }
            }
        }
    }

    public function delete(int $backupId, ?int $userId = null): bool
    {
        $backups = new Backup();
        $backup = $backups->find($backupId);
        if ($backup === null) {
            return false;
        }

        foreach (['files_path', 'database_path'] as $key) {
            if (!empty($backup[$key])) {
                @unlink(STORAGE_PATH . '/' . (string) $backup[$key]);
            }
        }

        $backups->deleteById($backupId);
        AuditLog::record('backup.deleted', 'backup', $backupId, [], $userId);

        return true;
    }

    public function absolutePath(string $relative): ?string
    {
        $absolute = realpath(STORAGE_PATH . '/' . ltrim($relative, '/'));
        $root = realpath($this->directory);

        if ($absolute === false || $root === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $absolute;
    }

    // -------------------------------------------------------------- Files --

    private function archiveFiles(string $destination, bool $includeUploads): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZipArchive extension is required to back up application files.');
        }

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $excluded = self::EXCLUDED_DIRS;
        if (!$includeUploads) {
            $excluded[] = 'uploads';
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(BASE_PATH, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $file) use ($excluded): bool {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(BASE_PATH) + 1));
                    foreach ($excluded as $skip) {
                        if ($relative === $skip || str_starts_with($relative, $skip . '/')) {
                            return false;
                        }
                    }

                    return true;
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(BASE_PATH) + 1));
            $zip->addFile($file->getPathname(), $relative);
            $count++;
        }

        $zip->setArchiveComment(json_encode([
            'created_at' => now(),
            'version'    => APP_VERSION,
            'files'      => $count,
        ], JSON_UNESCAPED_SLASHES) ?: '');

        $zip->close();

        if (!is_file($destination)) {
            throw new RuntimeException('The backup archive was not written.');
        }
    }

    /**
     * Directories whose contents are fully managed by a release. Only these
     * are pruned during a restore, so customer uploads and local storage are
     * never at risk.
     *
     * @var array<int,string>
     */
    private const MANAGED_DIRS = ['app', 'assets', 'config', 'database', 'bin', 'install'];

    private function restoreFiles(string $archive): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZipArchive extension is required to restore files.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Could not open the backup archive.');
        }

        $protected = UpdateService::protectedPaths();
        $archived = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (UpdateService::isProtected($name, $protected)) {
                continue;
            }
            if (str_contains($name, '..')) {
                continue;                               // zip-slip guard
            }

            $target = BASE_PATH . '/' . $name;
            $dir = dirname($target);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                continue;
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $contents = stream_get_contents($stream);
            fclose($stream);
            if ($contents !== false) {
                @file_put_contents($target, $contents, LOCK_EX);
                $archived[$name] = true;
            }
        }

        $zip->close();

        // A restore must be a true snapshot: remove files a failed update
        // added that the backup does not contain. Without this a broken
        // migration or a rogue file would survive the rollback.
        $this->pruneExtraneousFiles($archived, $protected);
    }

    /**
     * @param array<string,bool>  $archived Paths present in the backup
     * @param array<int,string>   $protected
     */
    private function pruneExtraneousFiles(array $archived, array $protected): int
    {
        $removed = 0;

        foreach (self::MANAGED_DIRS as $dir) {
            $absolute = BASE_PATH . '/' . $dir;
            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(BASE_PATH) + 1));

                if (isset($archived[$relative]) || UpdateService::isProtected($relative, $protected)) {
                    continue;
                }
                if (@unlink($item->getPathname())) {
                    $removed++;
                    Logger::info('Restore removed a file that was not in the backup', ['path' => $relative]);
                }
            }
        }

        return $removed;
    }

    // ----------------------------------------------------------- Database --

    private function dumpDatabase(string $destination): void
    {
        $db = Database::instance();
        $pdo = $db->pdo();
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the dump file for writing.');
        }

        $database = (string) Config::get('database.database', '');

        fwrite($handle, "-- Digital Visiting Card database backup\n");
        fwrite($handle, '-- Generated: ' . now() . "\n");
        fwrite($handle, '-- Version: ' . APP_VERSION . "\n");
        fwrite($handle, '-- Database: ' . $database . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($db->listTables() as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`');
            if ($create === false) {
                continue;
            }
            $row = $create->fetch(PDO::FETCH_NUM);
            if ($row === false) {
                continue;
            }

            fwrite($handle, "\n-- ----------------------------------------------------\n");
            fwrite($handle, '-- Table: ' . $table . "\n");
            fwrite($handle, "-- ----------------------------------------------------\n");
            fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
            fwrite($handle, $row[1] . ";\n\n");

            $statement = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`');
            if ($statement === false) {
                continue;
            }

            $batch = [];
            $columns = null;
            while (($record = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($columns === null) {
                    $columns = array_keys($record);
                }
                $values = [];
                foreach ($record as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string) $value;
                    } else {
                        $values[] = $pdo->quote((string) $value);
                    }
                }
                $batch[] = '(' . implode(',', $values) . ')';

                if (count($batch) >= 200) {
                    $this->writeInsert($handle, $table, $columns, $batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $this->writeInsert($handle, $table, (array) $columns, $batch);
            }
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($handle, "-- DUMP COMPLETE\n");
        fclose($handle);
    }

    /**
     * @param resource $handle
     * @param array<int,string> $columns
     * @param array<int,string> $rows
     */
    private function writeInsert($handle, string $table, array $columns, array $rows): void
    {
        if ($columns === [] || $rows === []) {
            return;
        }
        $columnList = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns));
        fwrite($handle, 'INSERT INTO `' . $table . '` (' . $columnList . ") VALUES\n" . implode(",\n", $rows) . ";\n");
    }

    private function importDatabase(string $dumpPath): void
    {
        $handle = fopen($dumpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not read the database dump.');
        }

        $pdo = Database::instance()->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $buffer = '';
        $inString = false;
        $stringChar = '';

        while (($line = fgets($handle)) !== false) {
            $trimmed = ltrim($line);
            if (!$inString && ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*'))) {
                continue;
            }

            $buffer .= $line;

            // Track quoting so a semicolon inside a value does not split the
            // statement.
            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                if ($inString) {
                    if ($char === '\\') {
                        $i++;
                    } elseif ($char === $stringChar) {
                        $inString = false;
                    }
                } elseif ($char === "'" || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                }
            }

            if (!$inString && preg_match('/;\s*$/', rtrim($line)) === 1) {
                $statement = trim($buffer);
                $buffer = '';
                if ($statement === '' || $statement === ';') {
                    continue;
                }
                $pdo->exec($statement);
            }
        }

        if (trim($buffer) !== '') {
            $pdo->exec(trim($buffer));
        }

        fclose($handle);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    // ---------------------------------------------------------- Retention --

    public function applyRetention(): int
    {
        $keep = max(1, (int) (Settings::get('backup_retention') ?: 5));
        $backups = new Backup();
        $completed = $backups->where(['status' => 'completed'], 'created_at DESC');

        $removed = 0;
        foreach (array_slice($completed, $keep) as $backup) {
            $this->delete((int) $backup['id']);
            $removed++;
        }

        return $removed;
    }

    private function tail(string $path, int $bytes): string
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        fseek($handle, max(0, $size - $bytes));
        $content = (string) fread($handle, $bytes);
        fclose($handle);

        return $content;
    }
}
