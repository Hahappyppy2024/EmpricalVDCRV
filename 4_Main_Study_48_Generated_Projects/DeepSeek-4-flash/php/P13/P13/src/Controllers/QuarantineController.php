<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\QuarantineService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Quarantine controller (MAIL-09). Requires domain_admin or system_admin
 * role.
 */
final class QuarantineController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private QuarantineService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = (string) ($params['status'] ?? '');
        $q = trim((string) ($params['q'] ?? ''));
        return $this->ok($response, ['quarantine' => $this->service->list($this->user(), $status, $q)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        if (isset($data['id'])) {
            $result = $this->service->action($this->user(), (int) $data['id'], (string) ($data['action'] ?? ''));
        } else {
            return $this->error($response, 'A quarantine id and action are required.', 422);
        }
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update($this->user(), (int) $args['id'], $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result);
    }
}
