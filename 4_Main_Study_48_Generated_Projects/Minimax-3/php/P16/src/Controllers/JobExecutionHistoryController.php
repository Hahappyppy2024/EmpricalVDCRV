<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JobRunRepository;
use App\Repositories\ScheduledJobRepository;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-06 — Job execution history.
 */
final class JobExecutionHistoryController
{
    public function __construct(
        private SessionService $session,
        private ScheduledJobRepository $jobs,
        private JobRunRepository $runs,
        private PhpRenderer $view
    ) {}

    public function showIndex(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $runs = $this->runs->recent(100);
        return $this->view->render($response, 'job_history_index.php', [
            'title' => 'Job execution history',
            'session' => $session,
            'runs' => $runs,
        ]);
    }

    public function showForJob(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $job = $this->jobs->findById($id);
        if (!$job) {
            $response->getBody()->write('Job not found');
            return $response->withStatus(404);
        }
        $runs = $this->runs->forJob($id, 100);
        return $this->view->render($response, 'job_history_detail.php', [
            'title' => 'Run history — ' . $job['name'],
            'session' => $session,
            'job' => $job,
            'runs' => $runs,
        ]);
    }

    public function showRun(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $run = $this->runs->findById($id);
        if (!$run) {
            $response->getBody()->write('Run not found');
            return $response->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$run['owner_id'] !== (int)$session['user_id']) {
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }
        return $this->view->render($response, 'job_history_run.php', [
            'title' => 'Run #' . $run['id'],
            'session' => $session,
            'run' => $run,
        ]);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $payload = json_encode(['runs' => $this->runs->recent(100)]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $id = (int)($args['id'] ?? 0);
        $run = $this->runs->findById($id);
        if (!$run) {
            $payload = json_encode(['ok' => false, 'error' => 'Run not found.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$run['owner_id'] !== (int)$session['user_id']) {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $payload = json_encode(['ok' => true, 'run' => $run]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}