<?php
declare(strict_types=1);

namespace Shop\Controllers;

class HttpRedirectException extends \RuntimeException
{
    public function __construct(public string $location, public int $status = 302)
    {
        parent::__construct('http_redirect_exception');
    }
}