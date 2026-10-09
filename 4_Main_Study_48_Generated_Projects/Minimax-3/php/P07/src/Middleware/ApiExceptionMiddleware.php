<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Infrastructure\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class ApiExceptionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $isApi = str_starts_with($path, '/api/');
        try {
            $response = $handler->handle($request);
            return $response;
        } catch (HttpException $e) {
            if ($isApi) {
                return $this->json($e->status, $e->getMessage(), $e->details);
            }
            return $this->html($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            if ($isApi) {
                return $this->json(500, 'internal_error', ['message' => $e->getMessage()]);
            }
            return $this->html(500, 'internal_error');
        }
    }

    private function json(int $status, string $error, array $details = []): ResponseInterface
    {
        $response = new Response();
        $payload = ['error' => $error];
        if ($details !== []) {
            $payload['details'] = $details;
        }
        $response->getBody()->write(json_encode($payload));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function html(int $status, string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write('<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>');
        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}