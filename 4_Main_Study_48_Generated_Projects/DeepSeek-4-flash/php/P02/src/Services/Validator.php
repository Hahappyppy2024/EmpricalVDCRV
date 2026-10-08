<?php

declare(strict_types=1);

namespace App\Services;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules field => rule ('required'|'email'|'string'|'int'|'in:a,b,c')
     * @return array<string, string>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $parts = explode('|', $rule);
            foreach ($parts as $part) {
                if ($part === 'required' && (self::isEmpty($value))) {
                    $errors[$field] = $field . ' is required';
                } elseif ($part === 'email' && !self::isEmpty($value) && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = $field . ' must be a valid email';
                } elseif ($part === 'string' && !self::isEmpty($value) && !is_string($value)) {
                    $errors[$field] = $field . ' must be a string';
                } elseif ($part === 'int' && !self::isEmpty($value) && !ctype_digit((string) $value) && !is_int($value)) {
                    $errors[$field] = $field . ' must be an integer';
                } elseif (str_starts_with($part, 'in:') && !self::isEmpty($value)) {
                    $allowed = explode(',', substr($part, 3));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[$field] = $field . ' must be one of: ' . implode(', ', $allowed);
                    }
                }
            }
        }
        return $errors;
    }

    public static function requireFields(array $data, array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = 'required';
        }
        return self::validate($data, $rules);
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    public static function trimArray(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }
}
