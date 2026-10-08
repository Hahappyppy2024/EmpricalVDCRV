<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ServerRequestInterface;

trait RouteSupport
{
    private static function body(ServerRequestInterface $request): array { return Http::data($request->getParsedBody()); }
    private static function required(array $data, array $fields): void {
        $missing = [];
        foreach ($fields as $field) if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) $missing[$field] = 'Required.';
        if ($missing) throw new ApiException(422, 'validation_failed', 'Please correct the highlighted fields.', $missing);
    }
    private static function one(PDO $db, string $sql, array $params, string $code = 'resource_not_found'): array {
        $stmt = $db->prepare($sql); $stmt->execute($params); $row = $stmt->fetch();
        if (!$row) throw new ApiException(404, $code, 'Resource not found.');
        return $row;
    }
    private static function all(PDO $db, string $sql, array $params = []): array { $stmt=$db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    private static function page(ServerRequestInterface $request): array {
        $q=$request->getQueryParams(); $limit=max(1,min(100,(int)($q['limit']??25))); $page=max(1,(int)($q['page']??1)); return [$limit,($page-1)*$limit,$page];
    }
    private static function publicUser(array $u): array { return array_intersect_key($u,array_flip(['id','email','display_name','role','bio','timezone'])); }
}
