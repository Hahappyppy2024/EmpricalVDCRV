<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal deterministic validation helpers.
 * Returns an array of errors keyed by field name.
 */
final class Validator
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            $value = $data[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field] = $label . ' is required.';
            }
        }
        return $errors;
    }

    public static function string(array $data, string $field, int $max = 255): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === null) {
            return null;
        }
        $value = (string)$value;
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new \InvalidArgumentException($field . ' exceeds maximum length.');
        }
        return $value;
    }

    public static function int(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException($field . ' must be an integer.');
        }
        return (int)$value;
    }

    public static function float(array $data, string $field): ?float
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException($field . ' must be numeric.');
        }
        return (float)$value;
    }

    public static function enum(array $data, string $field, array $allowed): ?string
    {
        $value = self::string($data, $field);
        if ($value === null) {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException($field . ' has an invalid value.');
        }
        return $value;
    }

    public static function email(array $data, string $field): ?string
    {
        $value = self::string($data, $field);
        if ($value === null) {
            return null;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException($field . ' must be a valid email address.');
        }
        return $value;
    }
}