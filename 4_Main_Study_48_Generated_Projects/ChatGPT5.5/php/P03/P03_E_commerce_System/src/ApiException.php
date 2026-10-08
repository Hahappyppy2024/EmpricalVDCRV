<?php
declare(strict_types=1);
namespace App;
use RuntimeException;
final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $apiCode, string $message, public readonly array $fields = [])
    { parent::__construct($message); }
}
