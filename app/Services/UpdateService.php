<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Settings;
use App\Middleware\Maintenance;
use App\Models\Backup;
use App\Models\UpdateLog;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * One-click GitHub updater.
 *
 * The update runs as an ordered pipeline; every stage is recorded on the
 * update_logs row so the administrator can see exactly where an update got
 * to. Any failure triggers an automatic rollback (files + database) and the
 * site stays in maintenance mode until the rollback itself has been
 * verified by the health check.
 */
final class UpdateService
{
    /** Paths that are never overwritten or deleted by an update. */
    public const DEFAULT_PROTECTED = [
        '.env',
        '.env.local',
        'config/local.php',
        'uploads/',
        'storage/',
        'install/.installed',
        '.htaccess.local',
        'robots.custom.txt',
    ];

    /**
     * Files shipped with the platform but owned by the server once they are
     * there: written when missing, never replaced.
     *
     * .user.ini carries the PHP limits on hosts where PHP is not an Apache
     * module, so a server that does not have it needs to receive it. But it
     * is also a file control panels manage: cPanel sets it immutable so a
     * site cannot override the PHP settings it hands out, and an update that
     * insisted on replacing it failed outright and rolled back.
     */
    public const SEED_ONLY = [
        '.user.ini',
    ];

    private UpdateLog $logs;

    private GitHubClient $github;

    public function __construct(?GitHubClient $github = null)
    {
        $this->logs = new UpdateLog();
        $this->github = $github ?? new GitHubClient();
    }

    public function github(): GitHubClient
    {
        return $this->github;
    }

    /** @return array<int,string> */
    public static function protectedPaths(): array
    {
        $configured = (string) (Settings::get('protected_paths') ?: '');
        $paths = self::DEFAULT_PROTECTED;

        foreach (preg_split('/[\r\n,]+/', $configured) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $paths[] = ltrim($line, '/');
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param array<int,string> $protected */
    public static function isProtected(string $relativePath, array $protected): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        foreach ($protected as $pattern) {
            $pattern = ltrim(str_replace('\\', '/', $pattern), '/');
            if ($pattern === '') {
                continue;
            }
            if (str_ends_with($pattern, '/')) {
                if (str_starts_with($relativePath . '/', $pattern)) {
                    return true;
                }

                continue;
            }
            if ($relativePath === $pattern || fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------- Check --

    /**
     * Check GitHub for a newer revision.
     *
     * @return array{available:bool,message:string,current_version:string,latest_version:string,current_commit:string,commit:array<string,mixed>,files:array<int,array<string,string>>,commits:array<int,array<string,mixed>>,error:?string}
     */
    public function check(): array
    {
        $current = $this->currentCommit();
        $result = [
            'available'       => false,
            'message'         => '',
            'current_version' => APP_VERSION,
            'latest_version'  => APP_VERSION,
            'current_commit'  => $current,
            'commit'          => [],
            'files'           => [],
            'commits'         => [],
            'error'           => null,
        ];

        if (!$this->github->isConfigured()) {
            $result['error'] = 'The GitHub repository has not been configured yet.';

            return $result;
        }

        try {
            $commit = $this->github->latestCommit();
            $result['commit'] = $commit;

            $remoteVersion = $this->remoteVersion($commit['sha']);
            $result['latest_version'] = $remoteVersion ?? APP_VERSION;

            if ($current !== '' && $current !== $commit['sha']) {
                $comparison = $this->github->compare($current, $commit['sha']);
                $result['files'] = $comparison['files'];
                $result['commits'] = $comparison['commits'];
            }

            $newerVersion = $remoteVersion !== null && version_compare($remoteVersion, APP_VERSION, '>');
            $newCommit = $current === '' || $current !== $commit['sha'];

            $result['available'] = $newerVersion || $newCommit;
            $result['message'] = $result['available']
                ? sprintf(
                    'Update available: %s (%s) by %s.',
                    $remoteVersion !== null && $remoteVersion !== APP_VERSION ? 'v' . $remoteVersion : 'commit ' . $commit['short'],
                    $commit['short'],
                    $commit['author']
                )
                : 'You are running the latest version (' . APP_VERSION . ').';

            Settings::set('github_last_check', now(), 'updates');
        } catch (Throwable $e) {
            Logger::error('Update check failed: ' . $e->getMessage());
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function currentCommit(): string
    {
        $stored = (string) (Settings::get('github_current_commit') ?: '');
        if ($stored !== '') {
            return $stored;
        }

        // Fall back to the local git checkout, if there is one.
        $head = BASE_PATH . '/.git/HEAD';
        if (is_file($head)) {
            $contents = trim((string) @file_get_contents($head));
            if (str_starts_with($contents, 'ref: ')) {
                $refFile = BASE_PATH . '/.git/' . trim(substr($contents, 5));
                if (is_file($refFile)) {
                    return trim((string) @file_get_contents($refFile));
                }
            } elseif (preg_match('/^[0-9a-f]{40}$/i', $contents) === 1) {
                return $contents;
            }
        }

        return '';
    }

    private function remoteVersion(string $ref): ?string
    {
        $contents = $this->github->fileContents('app/version.php', $ref);
        if ($contents === null) {
            return null;
        }
        if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $contents, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    // ------------------------------------------------------------- Update --

    /**
     * Run the full update pipeline.
     *
     * @return array{success:bool,message:string,log_id:int,steps:array<int,array<string,mixed>>,rolled_back:bool}
     */
    public function run(?int $userId = null): array
    {
        $started = microtime(true);
        $check = $this->check();

        if ($check['error'] !== null) {
            return $this->immediateFailure($check['error'], $userId);
        }
        if (!$check['available']) {
            return $this->immediateFailure('There is no update to install — you are already on the latest revision.', $userId);
        }

        $commit = $check['commit'];
        $logId = $this->logs->create([
            'from_version'   => APP_VERSION,
            'to_version'     => $check['latest_version'],
            'commit_hash'    => (string) ($commit['sha'] ?? ''),
            'commit_message' => mb_substr((string) ($commit['message'] ?? ''), 0, 480),
            'commit_date'    => !empty($commit['date']) ? date('Y-m-d H:i:s', (int) strtotime((string) $commit['date'])) : null,
            'branch'         => $this->github->branch(),
            'status'         => 'running',
            'stage'          => 'starting',
            'files_changed'  => count($check['files']),
            'files_list'     => array_slice($check['files'], 0, 500),
            'initiated_by'   => $userId,
            'started_at'     => now(),
        ]);

        $workDir = STORAGE_PATH . '/tmp/update-' . $logId;
        $backupId = null;
        $rolledBack = false;

        try {
            @set_time_limit(900);

            // 1 ------------------------------------------------ maintenance
            Maintenance::enable('We are installing an update. This usually takes less than a minute.', ['update_id' => $logId]);
            $this->logs->addStep($logId, 'maintenance_on', 'ok', 'Maintenance mode enabled.');

            // 2 ------------------------------------------- record current state
            $this->logs->addStep($logId, 'record_version', 'ok', 'Current version ' . APP_VERSION . ' (commit ' . substr($this->currentCommit(), 0, 8) . ').');

            // 3/4/5 --------------------------------------- backup + verify
            if ((bool) Settings::get('auto_backup_before_update', true)) {
                $backup = (new BackupService())->create('full', 'update', $userId);
                $backupId = (int) $backup['id'];

                if ((string) $backup['status'] !== 'completed') {
                    throw new RuntimeException('Pre-update backup failed: ' . (string) ($backup['error'] ?? 'unknown error'));
                }
                $this->logs->updateById($logId, ['backup_id' => $backupId, 'backup_status' => 'verified']);
                $this->logs->addStep($logId, 'backup', 'ok', 'Backup #' . $backupId . ' created and verified.');
            } else {
                $this->logs->updateById($logId, ['backup_status' => 'skipped']);
                $this->logs->addStep($logId, 'backup', 'warn', 'Backup skipped by configuration.');
            }

            // 6 --------------------------------------------------- download
            if (!is_dir($workDir) && !@mkdir($workDir, 0750, true) && !is_dir($workDir)) {
                throw new RuntimeException('Could not create the temporary update directory.');
            }
            $archivePath = $workDir . '/package.zip';
            $download = $this->github->downloadArchive($archivePath, (string) ($commit['sha'] ?? null));
            if (!$download['success']) {
                throw new RuntimeException($download['message']);
            }
            $this->logs->addStep($logId, 'download', 'ok', $download['message']);

            // 7 --------------------------------------------------- validate
            $extractDir = $workDir . '/extracted';
            $root = $this->validateAndExtract($archivePath, $extractDir);
            $this->logs->addStep($logId, 'validate', 'ok', 'Package validated and extracted.');

            // 8/9 ------------------------------- apply, preserving protected files
            $applied = $this->applyFiles($root, self::protectedPaths());
            $this->logs->addStep($logId, 'apply_files', 'ok', sprintf(
                '%d file(s) written, %d protected file(s) preserved.',
                $applied['written'],
                $applied['skipped']
            ));

            // 10/11 ----------------------------------------------- migrations
            $migrator = new Migrator(Database::instance());
            $pending = $migrator->pending();
            if ($pending !== []) {
                $migration = $migrator->run();
                if (!$migration['success']) {
                    throw new RuntimeException((string) $migration['error']);
                }
                $this->logs->updateById($logId, ['migration_status' => 'applied', 'migrations_run' => $migration['ran']]);
                $this->logs->addStep($logId, 'migrations', 'ok', count($migration['ran']) . ' migration(s) applied.');
            } else {
                $this->logs->updateById($logId, ['migration_status' => 'none']);
                $this->logs->addStep($logId, 'migrations', 'ok', 'No pending migrations.');
            }

            // 12/13 ------------------------------------------ cache + config
            $this->clearCaches();
            Settings::flush();
            Settings::load(true);
            Settings::set('sw_revision', substr((string) ($commit['sha'] ?? bin2hex(random_bytes(4))), 0, 8), 'general');
            $this->logs->addStep($logId, 'clear_cache', 'ok', 'Caches cleared and configuration reloaded.');

            // 14 ---------------------------------------------- health check
            $health = (new HealthCheck())->run();
            $this->logs->updateById($logId, [
                'health_status' => $health['healthy'] ? 'pass' : 'fail',
                'health_report' => $health,
            ]);

            if (!$health['healthy']) {
                throw new RuntimeException('Health check failed after update: ' . implode(', ', $health['failed']));
            }
            $this->logs->addStep($logId, 'health_check', 'ok', 'All health checks passed.');

            // 15 ------------------------------------------ record + reopen
            Settings::set('github_current_commit', (string) ($commit['sha'] ?? ''), 'updates');
            Settings::set('github_last_update', now(), 'updates');

            Maintenance::disable();
            $this->logs->addStep($logId, 'maintenance_off', 'ok', 'Maintenance mode disabled.');

            $this->logs->updateById($logId, [
                'status'           => 'success',
                'stage'            => 'complete',
                'duration_seconds' => (int) round(microtime(true) - $started),
                'finished_at'      => now(),
            ]);

            $this->cleanup($workDir);
            AuditLog::record('update.success', 'update_log', $logId, [
                'commit' => substr((string) ($commit['sha'] ?? ''), 0, 8),
                'files'  => $applied['written'],
            ], $userId);

            return [
                'success'     => true,
                'message'     => 'Update installed successfully.',
                'log_id'      => $logId,
                'steps'       => (array) ($this->logs->find($logId)['steps'] ?? []),
                'rolled_back' => false,
            ];
        } catch (Throwable $e) {
            Logger::error('Update failed: ' . $e->getMessage(), ['log_id' => $logId]);
            $this->logs->addStep($logId, 'failed', 'fail', $e->getMessage());
            $this->logs->updateById($logId, [
                'status'    => 'failed',
                'error_log' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);

            $rollback = $this->rollback($logId, $backupId, $userId);
            $rolledBack = $rollback['success'];

            $this->cleanup($workDir);

            AuditLog::record('update.failed', 'update_log', $logId, [
                'error'       => $e->getMessage(),
                'rolled_back' => $rolledBack,
            ], $userId);

            return [
                'success'     => false,
                'message'     => $rolledBack
                    ? 'Update failed and was rolled back automatically: ' . $e->getMessage()
                    : 'UPDATE FAILED AND THE ROLLBACK DID NOT COMPLETE. ' . $rollback['message']
                      . ' The site has been left in maintenance mode — restore backup #' . (string) $backupId . ' manually before reopening.',
                'log_id'      => $logId,
                'steps'       => (array) ($this->logs->find($logId)['steps'] ?? []),
                'rolled_back' => $rolledBack,
            ];
        }
    }

    // ------------------------------------------------------------ Rollback --

    /**
     * Restore the previous application state.
     *
     * @return array{success:bool,message:string}
     */
    public function rollback(int $logId, ?int $backupId = null, ?int $userId = null): array
    {
        $log = $this->logs->find($logId);
        $backupId = $backupId ?? ($log['backup_id'] ?? null);

        if ($backupId === null) {
            $this->logs->updateById($logId, ['rollback_status' => 'unavailable']);
            $this->logs->addStep($logId, 'rollback', 'fail', 'No backup was available to roll back to.');
            Maintenance::enable('An update failed and no automatic rollback was possible. The administrator has been notified.');

            return ['success' => false, 'message' => 'No pre-update backup exists.'];
        }

        try {
            $this->logs->addStep($logId, 'rollback_start', 'ok', 'Restoring backup #' . $backupId . '.');

            $restore = (new BackupService())->restore((int) $backupId, true, true, $userId);
            if (!$restore['success']) {
                throw new RuntimeException($restore['message']);
            }

            Settings::flush();
            Settings::load(true);
            $this->clearCaches();

            $health = (new HealthCheck())->run();
            if (!$health['healthy']) {
                $this->logs->updateById($logId, ['rollback_status' => 'failed', 'health_report' => $health]);
                $this->logs->addStep($logId, 'rollback', 'fail', 'Restored, but the health check still fails: ' . implode(', ', $health['failed']));
                Maintenance::enable('A failed update could not be fully rolled back. Please contact your administrator.');

                return ['success' => false, 'message' => 'The backup was restored but the health check still fails.'];
            }

            $this->logs->updateById($logId, [
                'status'          => 'rolled_back',
                'rollback_status' => 'success',
                'finished_at'     => now(),
            ]);
            $this->logs->addStep($logId, 'rollback', 'ok', 'Previous version restored and verified.');

            Maintenance::disable();
            $this->logs->addStep($logId, 'maintenance_off', 'ok', 'Maintenance mode disabled after rollback.');

            AuditLog::record('update.rolled_back', 'update_log', $logId, ['backup_id' => $backupId], $userId);

            return ['success' => true, 'message' => 'Rolled back to the previous version successfully.'];
        } catch (Throwable $e) {
            Logger::error('Rollback failed: ' . $e->getMessage(), ['log_id' => $logId]);
            $this->logs->updateById($logId, ['rollback_status' => 'failed']);
            $this->logs->addStep($logId, 'rollback', 'fail', $e->getMessage());
            Maintenance::enable('A failed update could not be rolled back automatically. Please restore a backup manually.');

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------- Files --

    /**
     * Validate and extract the downloaded archive.
     *
     * @return string Absolute path to the extracted repository root
     */
    private function validateAndExtract(string $archivePath, string $destination): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZipArchive extension is required to install updates.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath, ZipArchive::CHECKCONS);
        if ($opened !== true) {
            throw new RuntimeException('The update package is not a valid ZIP archive (code ' . $opened . ').');
        }
        if ($zip->numFiles < 10) {
            $zip->close();
            throw new RuntimeException('The update package looks incomplete (' . $zip->numFiles . ' files).');
        }

        // Guard against zip-slip before extracting anything.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();
                throw new RuntimeException('The update package contains an unsafe path: ' . $name);
            }
        }

        if (!is_dir($destination) && !@mkdir($destination, 0750, true) && !is_dir($destination)) {
            $zip->close();
            throw new RuntimeException('Could not create the extraction directory.');
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('Could not extract the update package (check disk space and permissions).');
        }
        $zip->close();

        // GitHub zipballs wrap everything in a single owner-repo-sha folder.
        $entries = array_values(array_filter(scandir($destination) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..'));
        $root = count($entries) === 1 && is_dir($destination . '/' . $entries[0])
            ? $destination . '/' . $entries[0]
            : $destination;

        foreach (['index.php', 'app/bootstrap.php', 'config/routes.php'] as $marker) {
            if (!is_file($root . '/' . $marker)) {
                throw new RuntimeException('The update package does not look like this application (missing ' . $marker . ').');
            }
        }

        return $root;
    }

    /**
     * Copy the extracted files over the installation.
     *
     * @param array<int,string> $protected
     * @return array{written:int,skipped:int}
     */
    /**
     * Why a file could not be replaced, in terms the operator can act on.
     * The directory is writable -- the new copy was created there -- so the
     * obstacle is the existing file itself.
     */
    private static function describeWriteFailure(string $target): string
    {
        if (!file_exists($target)) {
            return 'The file could not be created; check that ' . dirname($target) . ' is writable.';
        }

        $owner = 'unknown';
        if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
            $owner = posix_getpwuid((int) fileowner($target))['name'] ?? 'unknown';
        }
        $runningAs = 'unknown';
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $runningAs = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
        }
        $permissions = substr(sprintf('%o', (int) @fileperms($target)), -4);

        return sprintf(
            'It is owned by "%s" with permissions %s, and PHP runs as "%s". '
            . 'If ownership and permissions look right, the file may be locked by the hosting panel '
            . '(cPanel marks some files immutable); remove the lock or add the path to the protected list.',
            $owner,
            $permissions,
            $runningAs
        );
    }

    private function applyFiles(string $sourceRoot, array $protected): array
    {
        $written = 0;
        $skipped = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceRoot) + 1));
            if ($relative === '') {
                continue;
            }
            if (str_starts_with($relative, '.git/') || $relative === '.git') {
                continue;
            }
            if (self::isProtected($relative, $protected)) {
                $skipped++;

                continue;
            }

            $target = BASE_PATH . '/' . $relative;

            // Seeded files belong to the server once they exist.
            if (in_array($relative, self::SEED_ONLY, true) && file_exists($target)) {
                $skipped++;

                continue;
            }

            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new RuntimeException('Could not create directory: ' . $relative);
                }

                continue;
            }

            $dir = dirname($target);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Could not create directory: ' . dirname($relative));
            }

            // Write atomically so a half-written PHP file can never be loaded.
            $temp = $target . '.dvcnew';
            if (!@copy($item->getPathname(), $temp)) {
                throw new RuntimeException('Could not write ' . $relative . ' (check file permissions).');
            }
            if (!@rename($temp, $target)) {
                @unlink($temp);

                throw new RuntimeException(
                    'Could not replace ' . $relative . '. ' . self::describeWriteFailure($target)
                );
            }
            @chmod($target, 0644);
            $written++;
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    private function clearCaches(): void
    {
        $cacheDir = STORAGE_PATH . '/cache';
        foreach (glob($cacheDir . '/*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                @unlink($file);
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function cleanup(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    /** @return array{success:bool,message:string,log_id:int,steps:array<int,mixed>,rolled_back:bool} */
    private function immediateFailure(string $message, ?int $userId): array
    {
        $logId = $this->logs->create([
            'from_version' => APP_VERSION,
            'to_version'   => APP_VERSION,
            'branch'       => $this->github->branch(),
            'status'       => 'failed',
            'stage'        => 'pre_flight',
            'error_log'    => $message,
            'initiated_by' => $userId,
            'started_at'   => now(),
            'finished_at'  => now(),
        ]);

        return ['success' => false, 'message' => $message, 'log_id' => $logId, 'steps' => [], 'rolled_back' => false];
    }

    /** Clear a stale "running" update left behind by a PHP timeout. */
    public function clearStaleRun(): ?int
    {
        $running = $this->logs->runningUpdate();
        if ($running === null) {
            return null;
        }
        if (strtotime((string) $running['started_at']) > time() - 1800) {
            return null;                              // still plausibly running
        }

        $this->logs->updateById((int) $running['id'], [
            'status'      => 'failed',
            'error_log'   => 'The update process stopped unexpectedly (PHP timeout or fatal error).',
            'finished_at' => now(),
        ]);

        return (int) $running['id'];
    }
}
