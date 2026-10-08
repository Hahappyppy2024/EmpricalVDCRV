<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\ComposeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Message compose controller (MAIL-03).
 */
final class MessageComposeController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private ComposeService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $drafts = $this->service->list($this->userId());
        $contacts = $this->db->select(
            'SELECT id, first_name, last_name, email FROM contacts WHERE owner_user_id = ? ORDER BY id ASC',
            [$this->userId()]
        );
        return $this->ok($response, ['compose' => $drafts, 'contacts' => $contacts]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $action = (string) ($data['action'] ?? 'draft');
        if ($action === 'send') {
            $attachmentIds = isset($data['attachment_ids']) && is_array($data['attachment_ids'])
                ? array_map('intval', $data['attachment_ids'])
                : [];
            $result = $this->service->send(
                $this->userId(),
                (string) ($data['recipient'] ?? ''),
                (string) ($data['subject'] ?? ''),
                (string) ($data['body'] ?? ''),
                isset($data['draft_id']) && $data['draft_id'] ? (int) $data['draft_id'] : null,
                $attachmentIds
            );
        } else {
            $result = $this->service->saveDraft(
                $this->userId(),
                (string) ($data['recipient'] ?? ''),
                (string) ($data['subject'] ?? ''),
                (string) ($data['body'] ?? ''),
                isset($data['draft_id']) && $data['draft_id'] ? (int) $data['draft_id'] : null
            );
        }
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result, $action === 'send' ? 200 : 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->body($request);
        $result = $this->service->update(
            (int) $args['id'],
            $this->userId(),
            (string) ($data['recipient'] ?? ''),
            (string) ($data['subject'] ?? ''),
            (string) ($data['body'] ?? '')
        );
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['compose' => $result['compose'], 'message' => 'Draft updated.']);
    }
}
