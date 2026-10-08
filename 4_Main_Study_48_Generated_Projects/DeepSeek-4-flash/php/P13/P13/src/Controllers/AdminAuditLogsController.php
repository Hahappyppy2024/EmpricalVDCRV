<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Audit;
use P13\Database;
use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin audit logs controller (MAIL-10). Requires system_admin role.
 */
final class AdminAuditLogsController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private Audit $audit
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filters = [
            'action' => (string) ($params['action'] ?? ''),
            'entity_type' => (string) ($params['entity_type'] ?? ''),
            'role' => (string) ($params['role'] ?? ''),
            'q' => (string) ($params['q'] ?? ''),
            'from' => (string) ($params['from'] ?? ''),
            'to' => (string) ($params['to'] ?? ''),
        ];
        $result = $this->audit->query($filters, 100);
        return $this->ok($response, ['events' => $result['items'], 'total' => $result['total']]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $action = trim((string) ($data['action'] ?? ''));
        $entityType = trim((string) ($data['entity_type'] ?? 'AuditEvent'));
        if ($action === '') {
            return $this->error($response, 'An audit action is required.', 422);
        }
        $user = $this->user();
        $this->audit->log(
            (int) $user['id'],
            (string) $user['username'],
            (string) $user['role'],
            $action,
            $entityType,
            isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            is_array($data['details'] ?? null) ? $data['details'] : [],
            client_ip($request)
        );
        return $this->ok($response, ['message' => 'Audit event recorded.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->body($request);
        $row = $this->db->row('SELECT * FROM audit_events WHERE id = ?', [(int) $args['id']]);
        if ($row === null) {
            return $this->error($response, 'Unknown audit event.', 404);
        }
        if (isset($data['details']) && is_array($data['details'])) {
            $this->db->execute(
                'UPDATE audit_events SET details = ? WHERE id = ?',
                [json_encode($data['details']), (int) $args['id']]
            );
        }
        $updated = $this->db->row('SELECT * FROM audit_events WHERE id = ?', [(int) $args['id']]);
        $updated['details'] = json_decode((string) $updated['details'], true) ?: [];
        return $this->ok($response, ['event' => $updated, 'message' => 'Audit event updated.']);
    }
}
