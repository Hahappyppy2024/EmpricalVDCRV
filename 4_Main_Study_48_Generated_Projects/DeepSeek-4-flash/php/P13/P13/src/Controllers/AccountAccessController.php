<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Auth;
use P13\Database;
use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Account access API controller (MAIL-01): access history, password change,
 * access records.
 */
final class AccountAccessController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private Auth $auth
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $limit = isset($request->getQueryParams()['limit']) ? (int) $request->getQueryParams()['limit'] : 50;
        $limit = max(1, min(200, $limit));
        return $this->ok($response, ['access' => $this->auth->accessHistory($this->userId(), $limit)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $action = (string) ($data['action'] ?? 'record_access');
        if ($action === 'change_password') {
            $result = $this->auth->changePassword(
                $this->userId(),
                (string) ($data['current_password'] ?? ''),
                (string) ($data['new_password'] ?? '')
            );
            if (!$result['ok']) {
                return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
            }
            return $this->ok($response, $result);
        }
        if ($action === 'record_access') {
            $type = in_array($data['type'] ?? '', ['login', 'logout', 'api_access', 'password_change'], true)
                ? (string) $data['type']
                : 'api_access';
            $message = substr((string) ($data['message'] ?? 'API access recorded'), 0, 255);
            $this->db->execute(
                'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
                 VALUES (?, ?, \'success\', ?, ?, ?, datetime(\'now\'))',
                [$this->userId(), $type, $message, client_ip($request), client_ua($request)]
            );
            $id = $this->db->lastInsertId();
            return $this->ok($response, ['id' => $id, 'message' => 'Access recorded.'], 201);
        }
        return $this->error($response, 'Unknown account access action.', 422);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $row = $this->db->row(
            'SELECT * FROM account_access WHERE id = ? AND user_id = ?',
            [(int) $args['id'], $this->userId()]
        );
        if ($row === null) {
            return $this->error($response, 'Unknown or out-of-scope access record.', 404);
        }
        $data = $this->body($request);
        $message = substr((string) ($data['message'] ?? $row['message']), 0, 255);
        $this->db->execute(
            'UPDATE account_access SET message = ? WHERE id = ?',
            [$message, (int) $args['id']]
        );
        $updated = $this->db->row('SELECT * FROM account_access WHERE id = ?', [(int) $args['id']]);
        return $this->ok($response, ['access' => $updated, 'message' => 'Access record updated.']);
    }
}
