<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditRepository;
use App\Services\AuditLogger;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuditController
{
    public function __construct(private AuditRepository $audits) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $filters = (array)$request->getQueryParams();
        $items = $this->audits->listFiltered($filters, $user['role'], (int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'audit', ['user' => $user, 'items' => $items, 'filters' => $filters]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $row = $this->audits->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$row) return View::json($response, ['error' => 'not_found'], 404);
        return View::json($response, ['event' => $row]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $action = trim((string)($body['action'] ?? ''));
        $targetType = trim((string)($body['target_type'] ?? ''));
        $details = trim((string)($body['details'] ?? ''));
        if (!Validator::nonEmpty($action)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'action_required'], 422);
            return View::redirect($response, '/audit?error=action');
        }
        $id = $this->audits->append((int)$user['id'], $user['role'], $action, $targetType ?: null, null, $details ?: null, $this->ip($request));
        return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}