<?php

declare(strict_types=1);

namespace App;

final class ValidationException extends AppException
{
    /** @var array<string, string> */
    private array $fieldErrors;

    /** @param array<string, string> $fieldErrors */
    public function __construct(string $message, array $fieldErrors = [])
    {
        parent::__construct($message);
        $this->fieldErrors = $fieldErrors;
    }

    /** @return array<string, string> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
