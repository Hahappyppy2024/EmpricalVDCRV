<?php
declare(strict_types=1);

namespace Shop\Controllers;

class HttpJsonException extends \RuntimeException
{
    public function __construct(public int $status, public array $payload)
    {
        parent::__construct('http_json_exception');
    }
}