<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CronRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CronController
{
    public function __construct(private CronRepository $crons) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->crons->listForUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'cron', ['user' => $user, 'items' => $items, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['name'] ?? ''));
        $schedule = trim((string)($body['schedule'] ?? ''));
        $command = trim((string)($body['command'] ?? ''));
        $active = isset($body['status']) ? $body['status'] === 'active' : true;

        if (!Validator::nonEmpty($name) || !Validator::nonEmpty($command)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'validation'], 422);
            Flash::set('error', 'Name and command are required'); return View::redirect($response, '/cron?error=validation');
        }
        if (!preg_match('/^[\s*\/\d,A-Z\-?]+$/i', $schedule)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_schedule'], 422);
            Flash::set('error', 'Schedule must look like a cron expression'); return View::redirect($response, '/cron?error=schedule');
        }
        if ($this->crons->exists((int)$user['id'], $name)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'duplicate'], 409);
            Flash::set('error', 'A job with that name already exists'); return View::redirect($response, '/cron?error=duplicate');
        }
        $id = $this->crons->create((int)$user['id'], $name, $schedule, $command, $active);
        AuditLogger::log((int)$user['id'], $user['role'], 'cron.create', 'cron', $id, $name, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Job '$name' scheduled");
        return View::redirect($response, '/cron');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $job = $this->crons->findOwned((int)$args['id'], (int)$user['id']);
        if (!$job) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['name'] ?? $job['name']));
        $schedule = trim((string)($body['schedule'] ?? $job['schedule']));
        $command = trim((string)($body['command'] ?? $job['command']));
        $active = isset($body['status']) ? $body['status'] === 'active' : (bool)$job['is_active'];
        if (!Validator::nonEmpty($name) || !Validator::nonEmpty($command)) return View::json($response, ['error' => 'validation'], 422);
        $this->crons->update((int)$job['id'], (int)$user['id'], $name, $schedule, $command, $active);
        AuditLogger::log((int)$user['id'], $user['role'], 'cron.update', 'cron', (int)$job['id'], $name, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function run(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $job = $this->crons->findOwned((int)$args['id'], (int)$user['id']);
        if (!$job) return View::json($response, ['error' => 'not_found'], 404);
        $status = 'ok';
        // Deterministic local simulation: the job is recorded as executed
        $this->crons->markRun((int)$job['id'], (int)$user['id'], $status);
        AuditLogger::log((int)$user['id'], $user['role'], 'cron.run', 'cron', (int)$job['id'], $job['name'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => $status]);
        Flash::set('success', "Job '{$job['name']}' executed");
        return View::redirect($response, '/cron');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $job = $this->crons->findOwned((int)$args['id'], (int)$user['id']);
        if (!$job) return View::json($response, ['error' => 'not_found'], 404);
        $this->crons->delete((int)$job['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'cron.delete', 'cron', (int)$job['id'], $job['name'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'Job removed'); return View::redirect($response, '/cron');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}