<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class AuditController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private AuditService $audit,
        private PhpRenderer $view
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'audit.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $query = $request->getQueryParams();
        $filters = [
            'action' => (string) ($query['action'] ?? ''),
            'target_type' => (string) ($query['target_type'] ?? ''),
            'from' => (string) ($query['from'] ?? ''),
            'to' => (string) ($query['to'] ?? ''),
            'limit' => (int) ($query['limit'] ?? 100),
        ];
        $isAdmin = ($user['role'] ?? 'user') === 'admin';
        if (!$isAdmin) {
            $filters['user_id'] = (int) $user['id'];
        }
        $rows = $this->audit->search($filters);
        return $this->json($response, ['ok' => true, 'events' => $rows, 'exports' => $this->audit->listExports((int) $user['id'])]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $format = (string) ($data['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'json'], true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Export format must be csv or json.']], 422);
        }
        $filters = [
            'action' => (string) ($data['action'] ?? ''),
            'target_type' => (string) ($data['target_type'] ?? ''),
            'from' => (string) ($data['from'] ?? ''),
            'to' => (string) ($data['to'] ?? ''),
            'limit' => 500,
        ];
        if (($user['role'] ?? 'user') !== 'admin') {
            $filters['user_id'] = (int) $user['id'];
        }
        $export = $this->audit->export($filters, $format);
        $this->audit->recordExport((int) $user['id'], $format, $export['rows'], $export['filename']);
        $this->audit->log((int) $user['id'], 'export.created', 'audit', $format, ['rows' => $export['rows']]);
        return $this->json($response, [
            'ok' => true,
            'format' => $format,
            'filename' => $export['filename'],
            'rows' => $export['rows'],
            'content' => $export['content'],
            'message' => 'Audit export generated.',
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $id = (int) $args['id'];
        $event = $this->db->one('SELECT * FROM audit_events WHERE id = ?', [$id]);
        if (!$event || (($user['role'] ?? 'user') !== 'admin' && (int) $event['user_id'] !== (int) $user['id'])) {
            return $this->json($response, ['ok' => false, 'errors' => ['Audit event not found or out of scope.']], 404);
        }
        $data = $this->parseBody($request);
        $metadata = $event['metadata'];
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadata = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }
        $this->db->run('UPDATE audit_events SET metadata = ? WHERE id = ?', [$metadata, $id]);
        return $this->json($response, ['ok' => true, 'message' => 'Audit event annotation updated.']);
    }

    public function exportFile(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $format = (string) $args['format'];
        if (!in_array($format, ['csv', 'json'], true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Export format must be csv or json.']], 422);
        }
        $filters = ['limit' => 500];
        if (($user['role'] ?? 'user') !== 'admin') {
            $filters['user_id'] = (int) $user['id'];
        }
        $export = $this->audit->export($filters, $format);
        $response->getBody()->write($export['content']);
        return $response
            ->withHeader('Content-Type', $format === 'csv' ? 'text/csv' : 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"');
    }
}
