<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

final class JsonErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $errorLog = ''
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        if ($this->errorLog !== '') {
            $dir = dirname($this->errorLog);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $line = sprintf(
                "[%s] %s %s | %s: %s in %s:%d\n%s\n",
                date('Y-m-d H:i:s'),
                $request->getMethod(),
                (string) $request->getUri(),
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                substr($exception->getTraceAsString(), 0, 4000)
            );
            file_put_contents($this->errorLog, $line, FILE_APPEND);
        }

        $isNotFound = $exception instanceof HttpNotFoundException;
        $code = $isNotFound ? 'not_found' : 'internal_error';
        $status = $isNotFound ? 404 : 500;
        $message = $isNotFound
            ? 'The requested resource was not found.'
            : 'An unexpected error occurred. Please try again later.';

        $details = [];
        if ($displayErrorDetails && !$isNotFound) {
            $details['exception'] = get_class($exception);
        }

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'status' => $status,
                'message' => $message,
                'details' => $details,
            ],
        ]));
        return $response;
    }
}
