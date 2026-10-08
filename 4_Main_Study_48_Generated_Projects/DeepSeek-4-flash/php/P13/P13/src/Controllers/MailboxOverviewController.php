<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\MailboxService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Mailbox overview controller (MAIL-02).
 */
final class MailboxOverviewController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private MailboxService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $folderId = isset($params['folder_id']) && ctype_digit((string) $params['folder_id'])
            ? (int) $params['folder_id']
            : null;
        $q = trim((string) ($params['q'] ?? ''));
        return $this->ok($response, ['mailbox' => $this->service->overview($this->userId(), $folderId, $q)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $result = $this->service->createFolder($this->userId(), (string) ($data['name'] ?? ''));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['folder' => $result['folder'], 'message' => 'Folder created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->body($request);
        $result = $this->service->renameFolder((int) $args['id'], $this->userId(), (string) ($data['name'] ?? ''));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['folder' => $result['folder'], 'message' => 'Folder renamed.']);
    }
}
