<?php

declare(strict_types=1);

namespace App;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function required(array $data, string ...$fields): self
    {
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function string(array $data, string $field, int $max = 255, bool $required = false): self
    {
        $value = $data[$field] ?? null;
        if (($required && ($value === null || $value === ''))) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';

            return $this;
        }
        if ($value !== null && $value !== '') {
            if (!is_scalar($value)) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be text.';
            } elseif (mb_strlen((string) $value) > $max) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must not exceed ' . $max . ' characters.';
            }
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function number(array $data, string $field, ?float $min = null, ?float $max = null, bool $required = false): self
    {
        $value = $data[$field] ?? null;
        if ($required && ($value === null || $value === '')) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';

            return $this;
        }
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be numeric.';

            return $this;
        }
        $num = (float) $value;
        if ($min !== null && $num < $min) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be at least ' . $min . '.';
        }
        if ($max !== null && $num > $max) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be at most ' . $max . '.';
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function email(array $data, string $field, bool $required = false): self
    {
        $value = $data[$field] ?? null;
        if ($required && ($value === null || $value === '')) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';

            return $this;
        }
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'A valid email address is required.';
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function oneOf(array $data, string $field, array $allowed, bool $required = false): self
    {
        $value = $data[$field] ?? null;
        if (($required || $value !== null && $value !== '') && !in_array($value, $allowed, true)) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field))
                . ' must be one of: ' . implode(', ', $allowed) . '.';
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function unique(Database $db, string $table, string $column, mixed $value, ?int $ignoreId = null): self
    {
        $sql = 'SELECT id FROM ' . $table . ' WHERE ' . $column . ' = ?';
        $params = [$value];
        if ($ignoreId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        if ($db->fetchOne($sql, $params) !== null) {
            $this->errors[$column] = ucfirst(str_replace('_', ' ', $column)) . ' already exists.';
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;

        return $this;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function throwIfInvalid(): void
    {
        if ($this->fails()) {
            throw new ValidationException('Validation failed. Please review the highlighted fields.', $this->errors);
        }
    }
}
