<?php
declare(strict_types=1);

namespace LMS\Auth;

/**
 * Stable HTTP-style exception used by the AuthService / middleware.
 *
 * Mapped to JSON / HTML responses by the error handler.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
