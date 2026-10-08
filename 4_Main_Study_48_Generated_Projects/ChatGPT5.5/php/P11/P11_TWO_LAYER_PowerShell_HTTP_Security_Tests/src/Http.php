<?php
declare(strict_types=1);
namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Http
{
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
    public static function error(ResponseInterface $response, ApiException $e): ResponseInterface
    {
        return self::json($response, ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'fields' => $e->fields]], $e->status);
    }
    public static function body(ServerRequestInterface $request): array
    {
        $data = $request->getParsedBody();
        return is_array($data) ? $data : [];
    }
    public static function requireFields(array $data, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') $missing[$field] = 'required';
        }
        if ($missing) throw new ApiException(422, 'validation_error', 'Invalid request data.', $missing);
    }
    public static function page(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        $limit = isset($q['limit']) ? (int)$q['limit'] : 50;
        if ($limit < 1 || $limit > 100) throw new ApiException(422, 'validation_error', 'Invalid pagination.', ['limit' => 'must be 1..100']);
        return [$limit, max(0, (int)($q['offset'] ?? 0))];
    }
}
