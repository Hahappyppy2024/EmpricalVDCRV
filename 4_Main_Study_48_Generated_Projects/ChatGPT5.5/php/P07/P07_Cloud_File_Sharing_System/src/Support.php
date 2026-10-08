<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

trait Support
{
    private static function body(Request $request): array
    {
        $parsed = $request->getParsedBody();
        return is_array($parsed) ? $parsed : [];
    }

    private static function required(array $data, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[$field] = 'Required.';
            }
        }
        if ($missing) {
            throw new ApiException(422, 'validation_failed', 'Required fields are missing.', $missing);
        }
    }

    private static function one(PDO $db, string $sql, array $params = [], string $code = 'resource_not_found'): array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        if (!$row) {
            throw new ApiException(404, $code, 'Resource not found.');
        }
        return $row;
    }

    private static function all(PDO $db, string $sql, array $params = []): array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private static function page(Request $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 25)));
        return [$limit, ($page - 1) * $limit, $page];
    }
}
