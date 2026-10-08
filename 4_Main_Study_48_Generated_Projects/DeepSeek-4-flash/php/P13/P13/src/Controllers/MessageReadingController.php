<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\ReadingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Message reading controller (MAIL-04).
 */
final class MessageReadingController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private ReadingService $service
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
        $list = $this->service->list($this->userId(), $folderId, $q);
        return $this->ok($response, ['messages' => $list['items'], 'total' => $list['total']]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $detail = $this->service->detail((int) $args['id'], $this->userId());
        if ($detail === null) {
            return $this->error($response, 'Unknown or out-of-scope message.', 404);
        }
        return $this->ok($response, ['message' => $detail]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $messageId = isset($data['message_id']) ? (int) $data['message_id'] : 0;
        $result = $this->service->markOpened($messageId, $this->userId());
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->body($request);
        $result = $this->service->updateState(
            (int) $args['id'],
            $this->userId(),
            (string) ($data['status'] ?? 'read'),
            isset($data['starred']) && (int) $data['starred'] ? 1 : 0
        );
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result);
    }
}
