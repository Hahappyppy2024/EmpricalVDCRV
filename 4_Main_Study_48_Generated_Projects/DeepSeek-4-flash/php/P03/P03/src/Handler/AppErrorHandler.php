<?php

declare(strict_types=1);

namespace Shop\Handler;

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Handlers\ErrorHandler;
use Shop\DomainException;
use Shop\ValidationException;

/**
 * Deterministic user-safe error handler. No internal stack traces are ever
 * exposed; detailed information is written to the server log instead.
 */
final class AppErrorHandler extends ErrorHandler
{
    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        $isApi = str_starts_with($this->request->getUri()->getPath(), '/api');

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $message = 'The requested resource was not found.';
        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $message = 'The requested method is not allowed.';
        } elseif ($exception instanceof ValidationException) {
            $status = 422;
            $message = $exception->getMessage();
        } elseif ($exception instanceof DomainException) {
            $code = $exception->getCode();
            $status = ($code >= 400 && $code <= 499) ? $code : 500;
            $message = $status === 500 ? 'An unexpected error occurred.' : $exception->getMessage();
        } else {
            $status = 500;
            $message = 'An unexpected error occurred.';
        }

        $this->logError($exception->getMessage() . "\n" . $exception->getTraceAsString());

        if ($isApi) {
            $data = ['success' => false, 'error' => $message];
            if ($exception instanceof ValidationException) {
                $data['errors'] = $exception->errors();
            } elseif ($exception instanceof DomainException && $exception->errors() !== null) {
                $data['errors'] = $exception->errors();
            }
            $response = $this->responseFactory->createResponse($status);
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $code = $status === 500 ? '500' : (string) $status;
        $body = e($message);
        $html = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$code} - {$body}</title>
<link rel="stylesheet" href="/css/style.css"></head>
<body class="error-body">
<main class="error-card">
<h1>{$code}</h1>
<p>{$body}</p>
<a class="btn" href="/">Back to store</a>
</main>
</body>
</html>
HTML;
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
