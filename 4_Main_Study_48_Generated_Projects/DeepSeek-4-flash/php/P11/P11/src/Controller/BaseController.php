<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseController
{
    protected function render(RequestInterface $request, ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $data['request'] = $request;

        return Http::render($response, $template, $data);
    }

    protected function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return Http::redirect($response, $url);
    }

    protected function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        return Http::json($response, $payload, $status);
    }

    protected function error(ResponseInterface $response, string $message, int $status = 400): ResponseInterface
    {
        return Http::error($response, $message, $status);
    }

    protected function user(RequestInterface $request): array
    {
        $user = Http::user($request);
        if ($user === null) {
            throw new \RuntimeException('Route requires an authenticated user.');
        }

        return $user;
    }

    protected function requireAuth(RequestInterface $request, ResponseInterface $response): ?ResponseInterface
    {
        return Http::denyIfNotAuthenticated($request, $response);
    }

    protected function requireRole(RequestInterface $request, ResponseInterface $response, array $roles): ?ResponseInterface
    {
        return Http::denyIfRoleNotAllowed($request, $response, $roles);
    }

    protected function flash(RequestInterface $request, string $key, string $value): void
    {
        Http::setFlash($request, $key, $value);
    }
}
