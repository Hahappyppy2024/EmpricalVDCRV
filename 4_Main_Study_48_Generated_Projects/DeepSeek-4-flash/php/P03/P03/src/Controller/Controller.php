<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\DomainException;

abstract class Controller
{
    protected function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    protected function csv(ResponseInterface $response, string $filename, string $content): ResponseInterface
    {
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function html(ResponseInterface $response, string $content, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus($status);
    }

    protected function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }

    protected function user(ServerRequestInterface $request): ?array
    {
        return $request->getAttribute('user');
    }

    protected function requireUser(ServerRequestInterface $request): array
    {
        $user = $this->user($request);
        if ($user === null) {
            throw new DomainException('Authentication required.', 401);
        }
        return $user;
    }

    /**
     * @param list<string> $roles
     */
    protected function requireRole(ServerRequestInterface $request, array $roles): array
    {
        $user = $this->requireUser($request);
        if (!in_array($user['role'], $roles, true)) {
            throw new DomainException('You are not authorized to perform this action.', 403);
        }
        return $user;
    }

    protected function body(ServerRequestInterface $request): array
    {
        return (array) $request->getParsedBody();
    }

    protected function query(ServerRequestInterface $request): array
    {
        return (array) $request->getQueryParams();
    }

    protected function param(array $args, string $name, int $default = 0): int
    {
        return (int) ($args[$name] ?? $default);
    }
}
