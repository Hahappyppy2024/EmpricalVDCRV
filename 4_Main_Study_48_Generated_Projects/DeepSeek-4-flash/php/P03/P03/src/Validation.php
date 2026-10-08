<?php

declare(strict_types=1);

namespace Shop;

final class Validation
{
    public static function required(array $data, string ...$fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        return $errors;
    }

    public static function email(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function assertEmail(array &$errors, array $data, string $field): void
    {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value !== '' && !self::email($value)) {
            $errors[$field] = 'Invalid email address.';
        }
    }

    public static function int(mixed $value, int $min, int $max): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        return $filtered === false ? null : $filtered;
    }

    public static function float(mixed $value, float $min, float $max): ?float
    {
        $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($filtered === false) {
            return null;
        }
        return ($filtered < $min || $filtered > $max) ? null : $filtered;
    }

    public static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public static function throw(array $errors): void
    {
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
