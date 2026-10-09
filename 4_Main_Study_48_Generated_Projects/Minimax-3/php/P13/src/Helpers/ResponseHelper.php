<?php
declare(strict_types=1);

namespace MailServer\Helpers;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class ResponseHelper
{
    public static function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    public static function html(ResponseInterface $response, string $html, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($html);
        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function redirect(ResponseInterface $response, string $location, int $status = 302): ResponseInterface
    {
        return $response->withHeader('Location', $location)->withStatus($status);
    }

    public static function validationError(ResponseInterface $response, array $errors, int $status = 422): ResponseInterface
    {
        return self::json($response, ['error' => 'validation', 'fields' => $errors], $status);
    }

    public static function stableError(ResponseInterface $response, string $state, int $status = 400): ResponseInterface
    {
        return self::json($response, ['error' => 'stable_error', 'state' => $state], $status);
    }
}