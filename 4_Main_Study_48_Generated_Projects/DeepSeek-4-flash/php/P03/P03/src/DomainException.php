<?php

declare(strict_types=1);

namespace Shop;

/**
 * Deterministic, user-safe domain error. The HTTP status code is carried
 * with the exception so the error middleware can map it without leaking
 * internal details to the client.
 */
final class DomainException extends \RuntimeException
{
    /**
     * @param array<string,string>|null $errors
     */
    public function __construct(string $message, int $status = 400, private ?array $errors = null)
    {
        parent::__construct($message, $status);
    }

    /**
     * @return array<string,string>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
