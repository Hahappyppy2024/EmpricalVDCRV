<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

trait JsonResponder
{
    protected function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    protected function parseBody(Request $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return $request->getParsedBody() ?? [];
    }
}
