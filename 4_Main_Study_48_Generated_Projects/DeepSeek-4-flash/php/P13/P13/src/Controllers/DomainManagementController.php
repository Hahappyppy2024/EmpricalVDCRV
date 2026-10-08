<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\DomainService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Domain management controller (MAIL-08). Requires domain_admin or
 * system_admin role.
 */
final class DomainManagementController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private DomainService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
        return $this->ok($response, ['domains' => $this->service->list($this->user(), $q)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $result = $this->service->create(
            $this->user(),
            (string) ($data['name'] ?? ''),
            (int) ($data['quota_mb'] ?? 1024),
            (int) ($data['mailbox_limit'] ?? 25),
            is_array($data['aliases'] ?? null) ? $data['aliases'] : []
        );
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['domain' => $result['domain'], 'message' => 'Domain created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update($this->user(), (int) $args['id'], $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['domain' => $result['domain'], 'message' => 'Domain updated.']);
    }
}
