<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class Http
{
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function error(string $code, string $message, int $status, array $fields = []): ResponseInterface
    {
        return self::json(new Response(), ['error' => ['code' => $code, 'message' => $message, 'fields' => (object)$fields]], $status);
    }

    public static function data(mixed $body): array
    {
        return is_array($body) ? $body : [];
    }
}
