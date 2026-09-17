<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Forward-only database migrations.
 *
 * Each migration is a PHP file in database/migrations named
 * `YYYY_MM_DD_HHMMSS_description.php` returning an array with `up` and
 * (optionally) `down` closures which receive the Database instance.
 * Applied migrations are recorded in the `migrations` table with a batch
 * number so an update can roll a batch back.
 */
final class Migrator
{
    /** @var array<int,string> */
    private array $log = [];

    public function __construct(private readonly Database $db)
    {
    }

    public function ensureTable(): void
    {
        $table = $this->db->table('migrations');
        $this->db->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(190) NOT NULL,
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `checksum` CHAR(64) NULL,
                `executed_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<int,string> Migration names that have not run yet. */
    public function pending(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $pending = [];

        foreach ($this->files() as $name => $path) {
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    /** @return array<int,string> */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->db->select('SELECT `migration` FROM ' . $this->db->table('migrations') . ' ORDER BY `id`');

        return array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    /** @return array<string,string> name => absolute path */
    public function files(): array
    {
        $files = glob(DATABASE_PATH . '/migrations/*.php') ?: [];
        sort($files);
        $result = [];
        foreach ($files as $file) {
            $result[basename($file, '.php')] = $file;
        }

        return $result;
    }

    /**
     * Run every pending migration.
     *
     * @return array{success:bool,ran:array<int,string>,batch:int,error:?string}
     */
    public function run(): array
    {
        $this->ensureTable();
        $pending = $this->pending();
        $batch = $this->nextBatch();
        $ran = [];

        foreach ($pending as $name) {
            $path = $this->files()[$name] ?? null;
            if ($path === null) {
                continue;
            }

            try {
                $definition = require $path;
                if (!is_array($definition) || !isset($definition['up']) || !is_callable($definition['up'])) {
                    throw new \RuntimeException('Migration ' . $name . ' does not define an "up" closure.');
                }

                // DDL statements cause implicit commits in MySQL, so each
                // migration is executed on its own and failures stop the run.
                ($definition['up'])($this->db);

                $this->db->insert('migrations', [
                    'migration'   => $name,
                    'batch'       => $batch,
                    'checksum'    => hash_file('sha256', $path),
                    'executed_at' => now(),
                ]);

                $ran[] = $name;
                $this->log[] = 'Migrated: ' . $name;
            } catch (Throwable $e) {
                $message = 'Migration failed [' . $name . ']: ' . $e->getMessage();
                $this->log[] = $message;
                Logger::error($message);

                return ['success' => false, 'ran' => $ran, 'batch' => $batch, 'error' => $message];
            }
        }

        return ['success' => true, 'ran' => $ran, 'batch' => $batch, 'error' => null];
    }

    /**
     * Roll back the most recent batch (used when an update fails).
     *
     * @return array{success:bool,rolled:array<int,string>,error:?string}
     */
    public function rollbackBatch(?int $batch = null): array
    {
        $this->ensureTable();
        $batch ??= (int) $this->db->scalar('SELECT MAX(`batch`) FROM ' . $this->db->table('migrations'));
        if ($batch < 1) {
            return ['success' => true, 'rolled' => [], 'error' => null];
        }

        $rows = $this->db->select(
            'SELECT `migration` FROM ' . $this->db->table('migrations') . ' WHERE `batch` = :batch ORDER BY `id` DESC',
            ['batch' => $batch]
        );
        $files = $this->files();
        $rolled = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            $path = $files[$name] ?? null;
            if ($path === null) {
                continue;
            }
            try {
                $definition = require $path;
                if (is_array($definition) && isset($definition['down']) && is_callable($definition['down'])) {
                    ($definition['down'])($this->db);
                }
                $this->db->delete('migrations', ['migration' => $name]);
                $rolled[] = $name;
            } catch (Throwable $e) {
                $message = 'Rollback failed [' . $name . ']: ' . $e->getMessage();
                Logger::error($message);

                return ['success' => false, 'rolled' => $rolled, 'error' => $message];
            }
        }

        return ['success' => true, 'rolled' => $rolled, 'error' => null];
    }

    public function nextBatch(): int
    {
        return ((int) $this->db->scalar('SELECT MAX(`batch`) FROM ' . $this->db->table('migrations'))) + 1;
    }

    /** @return array<int,string> */
    public function log(): array
    {
        return $this->log;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $limit = 100): array
    {
        $this->ensureTable();

        return $this->db->select(
            'SELECT * FROM ' . $this->db->table('migrations') . ' ORDER BY `id` DESC LIMIT ' . max(1, min(500, $limit))
        );
    }
}
