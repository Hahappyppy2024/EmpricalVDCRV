<?php

declare(strict_types=1);

namespace App\Support;

final class Validation
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            $value = $data[$field] ?? '';
            if (trim((string) $value) === '') {
                $errors[] = ucfirst($label) . ' is required.';
            }
        }

        return $errors;
    }

    public static function str(array $data, string $field, int $min = 1, int $max = 255): string
    {
        $value = trim((string) ($data[$field] ?? ''));

        return mb_substr($value, 0, $max);
    }

    public static function int(array $data, string $field, int $default = 0): int
    {
        $value = filter_var($data[$field] ?? $default, FILTER_VALIDATE_INT);

        return $value === false ? $default : (int) $value;
    }

    public static function float(array $data, string $field, float $default = 0.0): float
    {
        $value = filter_var($data[$field] ?? $default, FILTER_VALIDATE_FLOAT);

        return $value === false ? $default : (float) $value;
    }

    public static function bool(array $data, string $field, bool $default = false): bool
    {
        if (!array_key_exists($field, $data)) {
            return $default;
        }

        return in_array($data[$field], [1, '1', 'on', 'true', true], true);
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validDomainName(string $value): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $value);
    }

    public static function in(array $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }
}
