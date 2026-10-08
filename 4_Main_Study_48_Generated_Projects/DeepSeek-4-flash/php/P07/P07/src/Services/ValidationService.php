<?php

declare(strict_types=1);

namespace CloudFS\Services;

final class ValidationService
{
    public function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[] = sprintf('The field "%s" is required.', $field);
            }
        }
        return $errors;
    }

    public function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function username(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $value);
    }

    public function integerInRange(string|int $value, int $min, int $max): bool
    {
        $value = (int) $value;
        return $value >= $min && $value <= $max;
    }

    public function extensionAllowed(string $filename, array $blockedExtensions): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return true;
        }
        return !in_array($ext, $blockedExtensions, true);
    }

    public function unique(array $errors, string $error): array
    {
        if (!empty($errors)) {
            return $errors;
        }
        return [$error];
    }
}
