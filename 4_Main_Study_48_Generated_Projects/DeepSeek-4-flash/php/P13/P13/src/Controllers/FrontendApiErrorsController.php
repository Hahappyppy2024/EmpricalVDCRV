<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\FrontendApiService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Frontend API integration and errors controller (MAIL-12).
 */
final class FrontendApiErrorsController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private FrontendApiService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->ok($response, ['errors' => $this->service->list($this->userId())]);
    }

    public function create(Request $request, Response $response): Response
    {
        $result = $this->service->record($this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['id' => $result['id'], 'message' => 'API error scenario recorded.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update((int) $args['id'], $this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['message' => 'Error scenario updated.']);
    }
}
