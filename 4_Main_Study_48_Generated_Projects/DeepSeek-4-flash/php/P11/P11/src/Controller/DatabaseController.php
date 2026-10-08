<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DatabaseService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DatabaseController extends BaseController
{
    private const ROLES = ['customer'];

    private DatabaseService $service;

    public function __construct()
    {
        $this->service = new DatabaseService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'databases.php', [
            'pageTitle' => 'Databases',
            'activeNav' => 'databases',
            'section' => 'list',
            'databases' => $this->service->listFor($user),
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'databases.php', [
            'pageTitle' => 'Create database',
            'activeNav' => 'databases',
            'section' => 'form',
            'database' => null,
        ]);
    }

    public function store(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->create($user, (string) ($body['name'] ?? ''));
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Database created.');
        if ($error === null) {
            return $this->redirect($response, '/databases/' . $id);
        }

        return $this->redirect($response, '/databases/new');
    }

    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $database = $this->service->getForUser((int) $args['id'], $user);
        if ($database === null) {
            return $this->error($response, 'Unknown or out-of-scope database.', 404);
        }

        return $this->render($request, $response, 'databases.php', [
            'pageTitle' => 'Database: ' . $database['name'],
            'activeNav' => 'databases',
            'section' => 'detail',
            'database' => $database,
        ]);
    }

    public function delete(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->delete($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Database deleted.');

        return $this->redirect($response, '/databases');
    }

    public function addUser(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $userId] = $this->service->addUser(
            $user,
            (int) $args['id'],
            (string) ($body['username'] ?? ''),
            (string) ($body['password'] ?? ''),
            (string) ($body['host'] ?? 'localhost'),
            (string) ($body['privileges'] ?? 'ALL')
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Database user added.');

        return $this->redirect($response, '/databases/' . $args['id']);
    }

    public function deleteUser(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->deleteUser($user, (int) $args['id'], (int) $args['uid']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Database user removed.');

        return $this->redirect($response, '/databases/' . $args['id']);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);

        return $this->json($response, ['success' => true, 'records' => $this->service->listFor($user)]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->create($user, (string) ($body['name'] ?? ''));
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Database configuration created.',
            'record' => $this->service->getForUser($id, $user),
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $databaseId = (int) $args['id'];

        if (isset($body['username'])) {
            [$error] = $this->service->addUser(
                $user,
                $databaseId,
                (string) $body['username'],
                (string) ($body['password'] ?? ''),
                (string) ($body['host'] ?? 'localhost'),
                (string) ($body['privileges'] ?? 'ALL')
            );
            if ($error !== null) {
                return $this->error($response, $error, 422);
            }

            return $this->json($response, ['success' => true, 'message' => 'Database user added.']);
        }

        return $this->error($response, 'Nothing to update.', 422);
    }
}
