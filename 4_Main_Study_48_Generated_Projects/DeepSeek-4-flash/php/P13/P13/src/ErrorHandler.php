<?php

declare(strict_types=1);

namespace P13;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Deterministic error handler: user-visible errors never leak stack traces.
 */
final class ErrorHandler
{
    public function __construct(private bool $debug)
    {
    }

    public function __invoke(Request $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails): Response
    {
        $response = new \Slim\Psr7\Response();
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            $payload = ['ok' => false, 'error' => 'Internal server error.'];
            if ($this->debug) {
                $payload['debug'] = [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }
            return json_response($response, $payload, 500);
        }
        $message = $this->debug ? e($exception->getMessage()) : 'Internal server error.';
        $html = '<html><body><h1>500</h1><p>' . $message . '</p><p><a href="/dashboard">Back to dashboard</a></p></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(500);
    }
}
