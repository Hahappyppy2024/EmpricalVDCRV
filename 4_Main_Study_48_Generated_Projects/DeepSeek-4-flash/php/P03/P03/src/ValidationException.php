<?php

declare(strict_types=1);

namespace Shop;

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string,string> $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct((string) ($errors['_error'] ?? 'Validation failed.'), 422);
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
