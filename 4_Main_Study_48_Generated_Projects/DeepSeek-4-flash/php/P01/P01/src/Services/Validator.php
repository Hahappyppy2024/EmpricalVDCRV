<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic field validation. Returns a map of field => error message.
 * An empty result means the input is valid.
 *
 * @phpstan-type Errors array<string, string>
 */
final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' field is required';
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            foreach ($ruleSet as $rule) {
                if (str_starts_with($rule, 'required') && ($value === null || (is_string($value) && trim($value) === ''))) {
                    $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' field is required';
                    break;
                }
                if (str_starts_with($rule, 'email') && is_string($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'A valid email address is required';
                    break;
                }
                if (str_starts_with($rule, 'min:') && is_string($value) && strlen($value) < (int) substr($rule, 4)) {
                    $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' must be at least ' . substr($rule, 4) . ' characters';
                    break;
                }
                if (str_starts_with($rule, 'in:') && is_string($value)) {
                    $allowed = array_map('trim', explode(',', substr($rule, 3)));
                    if (!in_array($value, $allowed, true)) {
                        $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' must be one of: ' . implode(', ', $allowed);
                        break;
                    }
                }
                if ($rule === 'integer' && $value !== null && $value !== '' && !is_int($value) && !ctype_digit((string) $value)) {
                    $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' must be an integer';
                    break;
                }
                if ($rule === 'numeric' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field] = 'The ' . str_replace('_', ' ', $field) . ' must be numeric';
                    break;
                }
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function take(array $data, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }
        return $out;
    }
}
