<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\RequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;

final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private readonly bool $isApi)
    {
    }

    public function __invoke(
        RequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): \Psr\Http\Message\ResponseInterface {
        $status = 500;
        $code = 'INTERNAL_ERROR';
        $message = 'An unexpected error occurred.';
        $fields = [];

        if ($exception instanceof ValidationException) {
            $status = 422;
            $code = 'VALIDATION';
            $message = $exception->getMessage();
            $fields = $exception->fieldErrors();
        } elseif ($exception instanceof ForbiddenException) {
            $status = 403;
            $code = 'FORBIDDEN';
            $message = $exception->getMessage();
        } elseif ($exception instanceof NotFoundException || $exception instanceof HttpNotFoundException) {
            $status = 404;
            $code = 'NOT_FOUND';
            $message = 'The requested record or resource was not found.';
        }

        $response = new Response($status);
        if ($displayErrorDetails) {
            $message = $exception->getMessage() . ' [' . get_class($exception) . ']';
        }
        if ($this->isApi || str_starts_with($request->getUri()->getPath(), '/api/')) {
            $payload = ['ok' => false, 'error' => $message, 'code' => $code];
            if ($fields !== []) {
                $payload['fields'] = $fields;
            }
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(
            '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"></head><body class="page-error">'
            . '<main class="card"><h1>Error ' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>'
            . '<a class="btn" href="/">Back to dashboard</a></main></body></html>'
        );

        return $response;
    }
}
