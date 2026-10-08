<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\ContactService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Contact management controller (MAIL-06).
 */
final class ContactManagementController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private ContactService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
        return $this->ok($response, ['contacts' => $this->service->list($this->userId(), $q)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $result = $this->service->create($this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['contact' => $result['contact'], 'message' => 'Contact created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update((int) $args['id'], $this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['contact' => $result['contact'], 'message' => 'Contact updated.']);
    }
}
