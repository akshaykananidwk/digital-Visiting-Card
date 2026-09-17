<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query in the application goes through here and
 * every value is bound — string concatenation of user input into SQL is
 * never performed.
 */
final class Database
{
    private static ?Database $instance = null;

    private ?PDO $pdo = null;

    private int $transactions = 0;

    private int $queryCount = 0;

    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @param array<string,mixed>|null $config */
    public static function instance(?array $config = null): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? (array) Config::get('database', []));
        }

        return self::$instance;
    }

    /** Reset the singleton (used by the installer and by tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** @param array<string,mixed> $config */
    public static function makeConnection(array $config): PDO
    {
        $socket = (string) ($config['socket'] ?? '');
        if ($socket !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $config['database'] ?? '', $config['charset'] ?? 'utf8mb4');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
        }

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ]);
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            try {
                $this->pdo = self::makeConnection($this->config);
            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed.', 0, $e);
            }
        }

        return $this->pdo;
    }

    public function prefix(): string
    {
        return (string) ($this->config['prefix'] ?? '');
    }

    /**
     * @param array<string|int,mixed> $bindings
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $this->queryCount++;

        // PDO (with emulation disabled) requires one placeholder per bound
        // value, so a named parameter reused in several conditions is
        // expanded into distinct placeholders here.
        [$sql, $bindings] = self::expandRepeatedPlaceholders($sql, $bindings);

        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array{0:string,1:array<string|int,mixed>}
     */
    public static function expandRepeatedPlaceholders(string $sql, array $bindings): array
    {
        if ($bindings === [] || !str_contains($sql, ':')) {
            return [$sql, $bindings];
        }

        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $name = ltrim((string) $key, ':');
            $pattern = '/:' . preg_quote($name, '/') . '\b/';
            $occurrences = preg_match_all($pattern, $sql);

            if ($occurrences === false || $occurrences < 2) {
                continue;
            }

            $index = 0;
            $sql = (string) preg_replace_callback($pattern, static function () use (&$index, $name): string {
                $index++;

                return $index === 1 ? ':' . $name : ':' . $name . '__r' . $index;
            }, $sql);

            for ($i = 2; $i <= $occurrences; $i++) {
                $bindings[$name . '__r' . $i] = $value;
            }
        }

        return [$sql, $bindings];
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $bindings */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->query($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $bindings */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table($table),
            implode(', ', array_map(static fn ($c) => '`' . $c . '`', $columns)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns))
        );
        $this->query($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            return 0;
        }
        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = '`' . $column . '` = :set_' . $column;
            $bindings['set_' . $column] = $value;
        }
        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = '`' . $column . '` = :where_' . $column;
            $bindings['where_' . $column] = $value;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->table($table),
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );

        return $this->execute($sql, $bindings);
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            return 0;
        }
        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = '`' . $column . '` = :' . $column;
        }
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $this->table($table), implode(' AND ', $conditions));

        return $this->execute($sql, $where);
    }

    public function table(string $name): string
    {
        return $this->prefix() . $name;
    }

    // ----------------------------------------------------------------- TX --

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactions + 1));
        }
        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo()->commit();
        }
        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions <= 1) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->transactions = 0;

            return;
        }
        $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactions);
        $this->transactions--;
    }

    /**
     * Run a callback inside a transaction; rolls back on any exception.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactions > 0;
    }

    // -------------------------------------------------------------- Schema --

    /** @var array<string,bool> */
    private array $schemaCache = [];

    public function tableExists(string $table): bool
    {
        $key = 't:' . $table;
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }

        $exists = (int) $this->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name',
            ['name' => $this->table($table)]
        ) > 0;

        return $this->schemaCache[$key] = $exists;
    }

    /** @return array<int,string> */
    public function listTables(): array
    {
        $rows = $this->select('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = (string) reset($row);
        }

        return $tables;
    }

    public function columnExists(string $table, string $column): bool
    {
        $key = 'c:' . $table . '.' . $column;
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }

        $exists = (int) $this->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => $this->table($table), 'column' => $column]
        ) > 0;

        return $this->schemaCache[$key] = $exists;
    }

    /** Forget cached schema lookups (used after a migration). */
    public function flushSchemaCache(): void
    {
        $this->schemaCache = [];
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function serverVersion(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
}
