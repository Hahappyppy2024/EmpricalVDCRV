<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ScheduledTaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TaskController extends BaseController
{
    private const ROLES = ['customer'];

    private ScheduledTaskService $service;

    public function __construct()
    {
        $this->service = new ScheduledTaskService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'tasks.php', [
            'pageTitle' => 'Scheduled tasks',
            'activeNav' => 'tasks',
            'section' => 'list',
            'tasks' => $this->service->listFor($user),
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'tasks.php', [
            'pageTitle' => 'New scheduled task',
            'activeNav' => 'tasks',
            'section' => 'form',
            'task' => null,
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

        [$error, $id] = $this->service->create(
            $user,
            (string) ($body['name'] ?? ''),
            (string) ($body['command'] ?? ''),
            (string) ($body['schedule'] ?? ''),
            isset($body['enabled']) && (int) $body['enabled'] === 1
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Scheduled task created.');
        if ($error === null) {
            return $this->redirect($response, '/cron');
        }

        return $this->redirect($response, '/cron/new');
    }

    public function update(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $task = $this->service->getForUser((int) $args['id'], $user);

        $error = $this->service->update(
            $user,
            (int) $args['id'],
            (string) ($body['name'] ?? ($task['name'] ?? '')),
            (string) ($body['command'] ?? ($task['command'] ?? '')),
            (string) ($body['schedule'] ?? ($task['schedule'] ?? '')),
            isset($body['enabled']) && (int) $body['enabled'] === 1
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Scheduled task updated.');

        return $this->redirect($response, '/cron');
    }

    public function toggle(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->toggle($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Task toggled.');

        return $this->redirect($response, '/cron');
    }

    public function run(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->runNow($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Task executed.');

        return $this->redirect($response, '/cron');
    }

    public function delete(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->delete($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Task deleted.');

        return $this->redirect($response, '/cron');
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

        [$error, $id] = $this->service->create(
            $user,
            (string) ($body['name'] ?? ''),
            (string) ($body['command'] ?? ''),
            (string) ($body['schedule'] ?? ''),
            isset($body['enabled']) && (int) $body['enabled'] === 1
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Scheduled task created.',
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
        $task = $this->service->getForUser((int) $args['id'], $user);

        $error = $this->service->update(
            $user,
            (int) $args['id'],
            (string) ($body['name'] ?? ($task['name'] ?? '')),
            (string) ($body['command'] ?? ($task['command'] ?? '')),
            (string) ($body['schedule'] ?? ($task['schedule'] ?? '')),
            isset($body['enabled']) && (int) $body['enabled'] === 1
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Scheduled task updated.',
            'record' => $this->service->getForUser((int) $args['id'], $user),
        ]);
    }
}
