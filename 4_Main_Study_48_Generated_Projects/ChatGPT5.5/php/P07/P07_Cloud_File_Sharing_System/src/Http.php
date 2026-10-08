<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;

final class Http
{
    public static function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function error(Response $response, ApiException $exception): Response
    {
        return self::json($response, ['error' => [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'fields' => $exception->fields,
        ]], $exception->status);
    }
}
