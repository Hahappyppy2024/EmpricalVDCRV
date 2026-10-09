<?php
declare(strict_types=1);

namespace Shop\Support;

final class Validator
{
    public static function email(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function password(string $password): bool
    {
        return strlen($password) >= 8;
    }

    public static function text(string $value, int $min = 1, int $max = 5000): bool
    {
        $len = mb_strlen(trim($value));
        return $len >= $min && $len <= $max;
    }

    public static function slug(string $value): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,80}$/', $value);
    }

    public static function int($value, ?int $min = null, ?int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $v = (int)$value;
        if ($min !== null && $v < $min) {
            return false;
        }
        if ($max !== null && $v > $max) {
            return false;
        }
        return true;
    }

    /** @return array<string,string> */
    public static function requireFields(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            $value = $data[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $errors[$field] = $label . ' is required.';
            }
        }
        return $errors;
    }

    public static function isValidTransition(string $from, string $to): bool
    {
        $allowed = [
            'pending' => ['paid', 'cancelled'],
            'paid' => ['shipped', 'cancelled', 'refunded'],
            'shipped' => ['delivered', 'refunded'],
            'delivered' => ['refunded'],
            'cancelled' => [],
            'refunded' => [],
        ];
        return in_array($to, $allowed[$from] ?? [], true);
    }
}