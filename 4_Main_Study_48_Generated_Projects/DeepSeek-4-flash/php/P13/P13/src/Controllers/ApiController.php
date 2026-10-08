<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Shared helpers for API controllers.
 */
abstract class ApiController
{
    public function __construct(protected Database $db, protected Session $session)
    {
    }

    protected function userId(): int
    {
        return (int) $this->session->userId();
    }

    protected function user(): array
    {
        $user = $this->session->user();
        return $user ?? [];
    }

    protected function body(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }
        return json_body($request);
    }

    protected function respond(Response $response, array $data, int $status = 200): Response
    {
        return json_response($response, $data, $status);
    }

    protected function error(Response $response, string $message, int $status = 400): Response
    {
        return error_response($response, $message, $status);
    }

    protected function ok(Response $response, array $data = [], int $status = 200): Response
    {
        return ok_response($response, $data, $status);
    }
}
