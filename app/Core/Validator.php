<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rule-based input validator.
 *
 * Supported rules: required, optional, nullable, string, email, url, numeric,
 * integer, boolean, alpha_dash, slug, phone, date, in:a,b, min:n, max:n,
 * between:a,b, confirmed, same:field, different:field, regex:/../,
 * unique:table,column[,ignoreId[,ignoreColumn]], exists:table,column,
 * array, json, hex_color, image, file.
 */
final class Validator
{
    /** @var array<string,array<int,string>> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $messages
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = []
    ) {
    }

    public function passes(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = array_filter(array_map('trim', explode('|', $ruleString)));
            $value = $this->value($field);

            $isRequired = in_array('required', $rules, true);
            $isNullable = in_array('nullable', $rules, true) || in_array('optional', $rules, true);
            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($isRequired && $isEmpty) {
                $this->addError($field, 'required', $this->label($field) . ' is required.');

                continue;
            }
            if ($isEmpty) {
                if (!$isRequired) {
                    $this->validated[$field] = $isNullable && $value === '' ? null : $value;
                }

                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable', 'optional'], true)) {
                    continue;
                }
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, (string) $name, $parameter, $value);
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $this->cast($rules, $value);
            }
        }

        return $this->errors === [];
    }

    private function applyRule(string $field, string $name, ?string $parameter, mixed $value): void
    {
        $label = $this->label($field);

        switch ($name) {
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, $name, $label . ' must be text.');
                }
                break;

            case 'email':
                if (filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false || strlen((string) $value) > 190) {
                    $this->addError($field, $name, $label . ' must be a valid email address.');
                }
                break;

            case 'url':
                if (filter_var((string) $value, FILTER_VALIDATE_URL) === false
                    || !preg_match('#^https?://#i', (string) $value)) {
                    $this->addError($field, $name, $label . ' must be a valid URL starting with http:// or https://.');
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, $name, $label . ' must be a number.');
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $name, $label . ' must be a whole number.');
                }
                break;

            case 'boolean':
                if (!in_array((string) $value, ['0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                    $this->addError($field, $name, $label . ' must be true or false.');
                }
                break;

            case 'alpha_dash':
                if (preg_match('/^[A-Za-z0-9_\-]+$/', (string) $value) !== 1) {
                    $this->addError($field, $name, $label . ' may only contain letters, numbers, dashes and underscores.');
                }
                break;

            case 'slug':
                if (preg_match('/^[a-z0-9][a-z0-9\-]{1,98}[a-z0-9]$/', (string) $value) !== 1) {
                    $this->addError($field, $name, $label . ' may only contain lowercase letters, numbers and dashes (3-100 characters).');
                }
                break;

            case 'phone':
                $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
                if (strlen($digits) < 7 || strlen($digits) > 15) {
                    $this->addError($field, $name, $label . ' must be a valid phone number.');
                }
                break;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, $name, $label . ' must be a valid date.');
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $parameter);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, $name, $label . ' must be one of: ' . implode(', ', $allowed) . '.');
                }
                break;

            case 'min':
                $min = (float) $parameter;
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value < $min) {
                        $this->addError($field, $name, $label . ' must be at least ' . $parameter . '.');
                    }
                } elseif (is_array($value)) {
                    if (count($value) < $min) {
                        $this->addError($field, $name, $label . ' must contain at least ' . $parameter . ' items.');
                    }
                } elseif (mb_strlen((string) $value) < $min) {
                    $this->addError($field, $name, $label . ' must be at least ' . $parameter . ' characters.');
                }
                break;

            case 'max':
                $max = (float) $parameter;
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value > $max) {
                        $this->addError($field, $name, $label . ' may not be greater than ' . $parameter . '.');
                    }
                } elseif (is_array($value)) {
                    if (count($value) > $max) {
                        $this->addError($field, $name, $label . ' may not contain more than ' . $parameter . ' items.');
                    }
                } elseif (mb_strlen((string) $value) > $max) {
                    $this->addError($field, $name, $label . ' may not be longer than ' . $parameter . ' characters.');
                }
                break;

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $parameter), 2, '0');
                $length = is_numeric($value) && !is_string($value) ? (float) $value : mb_strlen((string) $value);
                if ($length < (float) $low || $length > (float) $high) {
                    $this->addError($field, $name, $label . ' must be between ' . $low . ' and ' . $high . '.');
                }
                break;

            case 'confirmed':
                if ((string) $value !== (string) $this->value($field . '_confirmation')) {
                    $this->addError($field, $name, $label . ' confirmation does not match.');
                }
                break;

            case 'same':
                if ((string) $value !== (string) $this->value((string) $parameter)) {
                    $this->addError($field, $name, $label . ' must match ' . $this->label((string) $parameter) . '.');
                }
                break;

            case 'different':
                if ((string) $value === (string) $this->value((string) $parameter)) {
                    $this->addError($field, $name, $label . ' must be different from ' . $this->label((string) $parameter) . '.');
                }
                break;

            case 'regex':
                if (@preg_match((string) $parameter, (string) $value) !== 1) {
                    $this->addError($field, $name, $label . ' has an invalid format.');
                }
                break;

            case 'hex_color':
                if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', (string) $value) !== 1) {
                    $this->addError($field, $name, $label . ' must be a valid hex colour.');
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, $name, $label . ' must be a list.');
                }
                break;

            case 'json':
                if (!is_string($value) || json_decode($value) === null) {
                    $this->addError($field, $name, $label . ' must be valid JSON.');
                }
                break;

            case 'unique':
                if (!$this->checkUnique($parameter, $value)) {
                    $this->addError($field, $name, $label . ' is already taken.');
                }
                break;

            case 'exists':
                if (!$this->checkExists($parameter, $value)) {
                    $this->addError($field, $name, $this->label($field) . ' is invalid.');
                }
                break;

            case 'password':
                $password = (string) $value;
                if (mb_strlen($password) < 8
                    || preg_match('/[A-Za-z]/', $password) !== 1
                    || preg_match('/\d/', $password) !== 1) {
                    $this->addError($field, $name, 'Password must be at least 8 characters and include a letter and a number.');
                }
                break;
        }
    }

    private function checkUnique(?string $parameter, mixed $value): bool
    {
        [$table, $column, $ignoreId, $ignoreColumn] = array_pad(explode(',', (string) $parameter), 4, null);
        if ($table === null || $column === null) {
            return true;
        }
        $db = Database::instance();
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $db->table($table), $this->safeIdentifier($column));
        $bindings = ['value' => $value];

        if ($ignoreId !== null && $ignoreId !== '' && $ignoreId !== 'null') {
            $sql .= sprintf(' AND `%s` <> :ignore', $this->safeIdentifier($ignoreColumn ?? 'id'));
            $bindings['ignore'] = $ignoreId;
        }
        if ($db->columnExists($table, 'deleted_at')) {
            $sql .= ' AND `deleted_at` IS NULL';
        }

        return (int) $db->scalar($sql, $bindings) === 0;
    }

    private function checkExists(?string $parameter, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', (string) $parameter), 2, 'id');
        if ($table === null) {
            return true;
        }
        $db = Database::instance();
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $db->table($table), $this->safeIdentifier((string) $column));

        return (int) $db->scalar($sql, ['value' => $value]) > 0;
    }

    private function safeIdentifier(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $identifier) ?? 'id';
    }

    /** @param array<int,string> $rules */
    private function cast(array $rules, mixed $value): mixed
    {
        if (in_array('integer', $rules, true)) {
            return (int) $value;
        }
        if (in_array('numeric', $rules, true)) {
            return (float) $value;
        }
        if (in_array('boolean', $rules, true)) {
            return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
        }
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function value(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        return array_get($this->data, $field);
    }

    private function label(string $field): string
    {
        return ucfirst(str_replace(['_', '.'], ' ', $field));
    }

    private function addError(string $field, string $rule, string $message): void
    {
        $this->errors[$field][] = $this->messages[$field . '.' . $rule] ?? $this->messages[$field] ?? $message;
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }
}
