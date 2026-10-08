<?php

declare(strict_types=1);

namespace App\Services;

final class WorkflowException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $details = [],
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
