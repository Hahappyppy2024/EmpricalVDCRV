<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\RuleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Filters and rules controller (MAIL-07).
 */
final class FiltersAndRulesController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private RuleService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->ok($response, ['rules' => $this->service->list($this->userId())]);
    }

    public function create(Request $request, Response $response): Response
    {
        $result = $this->service->create($this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['rule' => $result['rule'], 'message' => 'Rule created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update((int) $args['id'], $this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['rule' => $result['rule'], 'message' => 'Rule updated.']);
    }
}
