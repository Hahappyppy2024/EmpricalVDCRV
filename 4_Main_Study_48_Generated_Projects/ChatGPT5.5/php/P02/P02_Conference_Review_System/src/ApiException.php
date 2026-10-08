<?php
declare(strict_types=1);
namespace App;
use RuntimeException;
final class ApiException extends RuntimeException{public readonly string $apiCode;public function __construct(public readonly int $status,string $code,string $message,public readonly array $fields=[]){parent::__construct($message);$this->apiCode=$code;}}
