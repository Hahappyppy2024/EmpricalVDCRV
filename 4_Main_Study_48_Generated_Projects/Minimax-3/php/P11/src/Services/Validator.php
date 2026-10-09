<?php
declare(strict_types=1);

namespace App\Services;

final class Validator
{
    public static function nonEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function domain(string $value): bool
    {
        return (bool)preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/i', $value);
    }

    public static function username(string $value): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $value);
    }

    public static function int(string $value): bool
    {
        return ctype_digit(trim($value));
    }

    public static function inList(string $value, array $list): bool
    {
        return in_array($value, $list, true);
    }

    public static function slug(string $value): bool
    {
        return (bool)preg_match('/^[a-z0-9_-]{1,64}$/i', $value);
    }

    public static function oneOf(string $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }
}