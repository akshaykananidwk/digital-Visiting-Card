<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Lightweight repository base class. Provides safe CRUD, soft deletes,
 * pagination and JSON column casting. All identifiers used in SQL are drawn
 * from the model definition (never from request input) and every value is
 * bound through PDO.
 */
abstract class Model
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    protected bool $timestamps = true;

    protected bool $softDeletes = false;

    /** @var array<int,string> Columns that may be mass-assigned. */
    protected array $fillable = [];

    /** @var array<int,string> Columns stored as JSON. */
    protected array $jsonColumns = [];

    /** @var array<int,string> Columns cast to int on read. */
    protected array $intColumns = [];

    /** @var array<int,string> Columns cast to float on read. */
    protected array $floatColumns = [];

    protected Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    public function table(): string
    {
        return $this->db->table($this->table);
    }

    public function rawTable(): string
    {
        return $this->table;
    }

    // -------------------------------------------------------------- Read --

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = :id', $this->table(), $this->primaryKey);
        if ($this->softDeletes) {
            $sql .= ' AND `deleted_at` IS NULL';
        }

        return $this->hydrate($this->db->selectOne($sql . ' LIMIT 1', ['id' => $id]));
    }

    /** @return array<string,mixed>|null */
    public function findBy(string $column, mixed $value): ?array
    {
        $column = $this->safeColumn($column);
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = :value', $this->table(), $column);
        if ($this->softDeletes) {
            $sql .= ' AND `deleted_at` IS NULL';
        }

        return $this->hydrate($this->db->selectOne($sql . ' LIMIT 1', ['value' => $value]));
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array<string,mixed>|null
     */
    public function firstWhere(array $conditions): ?array
    {
        [$sql, $bindings] = $this->buildWhere($conditions);

        return $this->hydrate($this->db->selectOne(
            sprintf('SELECT * FROM `%s`%s LIMIT 1', $this->table(), $sql),
            $bindings
        ));
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array<int,array<string,mixed>>
     */
    public function where(array $conditions, string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        [$sql, $bindings] = $this->buildWhere($conditions);
        $query = sprintf('SELECT * FROM `%s`%s', $this->table(), $sql);
        $query .= $this->orderClause($orderBy);
        if ($limit > 0) {
            $query .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }

        return $this->hydrateAll($this->db->select($query, $bindings));
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = '', int $limit = 0): array
    {
        return $this->where([], $orderBy, $limit);
    }

    /** @param array<string,mixed> $conditions */
    public function count(array $conditions = []): int
    {
        [$sql, $bindings] = $this->buildWhere($conditions);

        return (int) $this->db->scalar(sprintf('SELECT COUNT(*) FROM `%s`%s', $this->table(), $sql), $bindings);
    }

    /** @param array<string,mixed> $conditions */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Paginate results.
     *
     * @param array<string,mixed> $conditions
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function paginate(array $conditions = [], int $page = 1, int $perPage = 20, string $orderBy = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $total = $this->count($conditions);
        $pages = (int) max(1, ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $data = $this->where($conditions, $orderBy, $perPage, $offset);

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
            'from'     => $total === 0 ? 0 : $offset + 1,
            'to'       => $offset + count($data),
        ];
    }

    // ------------------------------------------------------------- Write --

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data = $this->prepare($data);
        if ($this->timestamps) {
            $data['created_at'] = $data['created_at'] ?? now();
            if ($this->db->columnExists($this->table, 'updated_at')) {
                $data['updated_at'] = $data['updated_at'] ?? now();
            }
        }

        return $this->db->insert($this->table, $data);
    }

    /** @param array<string,mixed> $data */
    public function updateById(int $id, array $data): int
    {
        $data = $this->prepare($data);
        if ($data === []) {
            return 0;
        }
        if ($this->timestamps && $this->db->columnExists($this->table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        return $this->db->update($this->table, $data, [$this->primaryKey => $id]);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $conditions
     */
    public function updateWhere(array $conditions, array $data): int
    {
        $data = $this->prepare($data);
        if ($data === [] || $conditions === []) {
            return 0;
        }
        if ($this->timestamps && $this->db->columnExists($this->table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        return $this->db->update($this->table, $data, $conditions);
    }

    public function deleteById(int $id): int
    {
        if ($this->softDeletes) {
            return $this->db->update($this->table, ['deleted_at' => now()], [$this->primaryKey => $id]);
        }

        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function forceDeleteById(int $id): int
    {
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function restoreById(int $id): int
    {
        if (!$this->softDeletes) {
            return 0;
        }

        return $this->db->update($this->table, ['deleted_at' => null], [$this->primaryKey => $id]);
    }

    public function increment(int $id, string $column, int $amount = 1): int
    {
        $column = $this->safeColumn($column);

        return $this->db->execute(
            sprintf('UPDATE `%s` SET `%s` = `%s` + :amount WHERE `%s` = :id', $this->table(), $column, $column, $this->primaryKey),
            ['amount' => $amount, 'id' => $id]
        );
    }

    // ---------------------------------------------------------- Internals --

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function prepare(array $data): array
    {
        if ($this->fillable !== []) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }
        foreach ($this->jsonColumns as $column) {
            if (array_key_exists($column, $data) && is_array($data[$column])) {
                $data[$column] = json_encode($data[$column], JSON_UNESCAPED_UNICODE);
            }
        }
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = $value ? 1 : 0;
            }
        }

        return $data;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    protected function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        foreach ($this->jsonColumns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $row[$column] === null ? null : json_decode_safe((string) $row[$column]);
            }
        }
        foreach ($this->intColumns as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (int) $row[$column];
            }
        }
        foreach ($this->floatColumns as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (float) $row[$column];
            }
        }

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    protected function hydrateAll(array $rows): array
    {
        return array_map(fn (array $row): array => (array) $this->hydrate($row), $rows);
    }

    /**
     * Build a WHERE clause. Supports:
     *   'column' => value                    → `column` = value
     *   'column' => ['in', [1,2,3]]          → `column` IN (...)
     *   'column' => ['like', 'abc']          → `column` LIKE %abc%
     *   'column' => ['>=', 10]               → `column` >= 10
     *   'column' => null                     → `column` IS NULL
     *   '__raw'  => ['sql', [bindings]]      → raw fragment (never user input)
     *
     * @param array<string,mixed> $conditions
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function buildWhere(array $conditions): array
    {
        $clauses = [];
        $bindings = [];
        $index = 0;

        if ($this->softDeletes && !array_key_exists('__with_trashed', $conditions)) {
            $clauses[] = '`deleted_at` IS NULL';
        }
        unset($conditions['__with_trashed']);

        foreach ($conditions as $column => $value) {
            if ($column === '__raw' && is_array($value)) {
                $clauses[] = '(' . (string) $value[0] . ')';
                foreach ((array) ($value[1] ?? []) as $key => $bind) {
                    $bindings[(string) $key] = $bind;
                }

                continue;
            }

            $safe = $this->safeColumn((string) $column);
            $placeholder = 'w' . $index++;

            if ($value === null) {
                $clauses[] = sprintf('`%s` IS NULL', $safe);

                continue;
            }

            if (is_array($value) && count($value) === 2 && is_string($value[0])) {
                $operator = strtolower((string) $value[0]);
                $operand = $value[1];

                switch ($operator) {
                    case 'in':
                        $list = (array) $operand;
                        if ($list === []) {
                            $clauses[] = '1 = 0';
                            break;
                        }
                        $names = [];
                        foreach (array_values($list) as $i => $item) {
                            $name = $placeholder . '_' . $i;
                            $names[] = ':' . $name;
                            $bindings[$name] = $item;
                        }
                        $clauses[] = sprintf('`%s` IN (%s)', $safe, implode(', ', $names));
                        break;

                    case 'not in':
                        $list = (array) $operand;
                        if ($list === []) {
                            break;
                        }
                        $names = [];
                        foreach (array_values($list) as $i => $item) {
                            $name = $placeholder . '_' . $i;
                            $names[] = ':' . $name;
                            $bindings[$name] = $item;
                        }
                        $clauses[] = sprintf('`%s` NOT IN (%s)', $safe, implode(', ', $names));
                        break;

                    case 'like':
                        $clauses[] = sprintf('`%s` LIKE :%s', $safe, $placeholder);
                        $bindings[$placeholder] = '%' . $this->escapeLike((string) $operand) . '%';
                        break;

                    case 'starts':
                        $clauses[] = sprintf('`%s` LIKE :%s', $safe, $placeholder);
                        $bindings[$placeholder] = $this->escapeLike((string) $operand) . '%';
                        break;

                    case 'is not null':
                        $clauses[] = sprintf('`%s` IS NOT NULL', $safe);
                        break;

                    case '=': case '!=': case '<>': case '>': case '>=': case '<': case '<=':
                        $clauses[] = sprintf('`%s` %s :%s', $safe, $operator, $placeholder);
                        $bindings[$placeholder] = $operand;
                        break;

                    default:
                        $clauses[] = sprintf('`%s` = :%s', $safe, $placeholder);
                        $bindings[$placeholder] = $operand;
                }

                continue;
            }

            $clauses[] = sprintf('`%s` = :%s', $safe, $placeholder);
            $bindings[$placeholder] = $value;
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** Order clause built only from a whitelisted column/direction pair. */
    protected function orderClause(string $orderBy): string
    {
        if (trim($orderBy) === '') {
            return '';
        }
        $parts = preg_split('/\s+/', trim($orderBy)) ?: [];
        $column = $this->safeColumn((string) ($parts[0] ?? $this->primaryKey));
        $direction = strtoupper((string) ($parts[1] ?? 'ASC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';

        return sprintf(' ORDER BY `%s` %s', $column, $direction);
    }

    protected function safeColumn(string $column): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $column) ?? '';

        return $clean === '' ? $this->primaryKey : $clean;
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function db(): Database
    {
        return $this->db;
    }
}
