<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Global exception handler. All uncaught exceptions and HTTP errors are
 * rendered as stable, generic responses that never leak internal stack
 * traces: JSON for /api paths, a simple HTML page otherwise.
 */
final class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $status = 500;
            if ($e instanceof \Slim\Exception\HttpException) {
                $status = $e->getCode();
                $message = $e->getMessage();
            } else {
                $message = 'An unexpected error occurred. Please try again later.';
            }
            if (str_starts_with((string) $request->getUri()->getPath(), '/api/')) {
                return \App\Http::json($this->responseFactory->createResponse($status), $status, [
                    'ok' => false,
                    'error' => ['code' => $status === 404 ? 'NOT_FOUND' : 'INTERNAL_ERROR', 'message' => $status === 404 ? 'Resource not found' : $message],
                ]);
            }
            $body = '<!doctype html><meta charset="utf-8"><title>Error</title>'
                . '<style>body{font-family:sans-serif;max-width:600px;margin:80px auto;padding:0 20px}'
                . 'code{background:#f4f4f4;padding:2px 6px;border-radius:4px}</style>'
                . '<h1>Something went wrong</h1><p>' . htmlspecialchars($status === 404 ? 'The page you requested does not exist.' : $message) . '</p>'
                . '<p><a href="/dashboard">&larr; Back to dashboard</a></p>';
            $response = $this->responseFactory->createResponse($status);
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
    }
}
