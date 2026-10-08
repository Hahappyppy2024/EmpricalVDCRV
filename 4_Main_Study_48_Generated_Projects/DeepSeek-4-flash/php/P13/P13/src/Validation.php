<?php

declare(strict_types=1);

namespace P13;

/**
 * Deterministic validation helpers.
 */
final class Validation
{
    public const ROLES = ['mail_user', 'domain_admin', 'system_admin'];

    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field] = "The field '{$field}' is required.";
            }
        }
        return $errors;
    }

    public static function email(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function role(mixed $value): bool
    {
        return in_array($value, self::ROLES, true);
    }

    public static function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    public static function intPositive(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    public static function intRange(mixed $value, int $min, int $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $v = (int) $value;
        return $v >= $min && $v <= $max;
    }

    public static function stringLen(mixed $value, int $max): bool
    {
        return is_string($value) && mb_strlen($value) <= $max;
    }
}
