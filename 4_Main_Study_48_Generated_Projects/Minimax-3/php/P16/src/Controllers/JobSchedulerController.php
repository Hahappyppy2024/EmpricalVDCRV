<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JobProfileRepository;
use App\Repositories\JobRunRepository;
use App\Repositories\ScheduledJobRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-05 — Job scheduler.
 */
final class JobSchedulerController
{
    public function __construct(
        private SessionService $session,
        private ScheduledJobRepository $jobs,
        private JobProfileRepository $profiles,
        private JobRunRepository $runs,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $jobs = $this->jobs->all($session['role'] === 'admin' ? null : (int)$session['user_id']);
        $profiles = $this->profiles->all();
        return $this->view->render($response, 'job_scheduler.php', [
            'title' => 'Job scheduler',
            'session' => $session,
            'jobs' => $jobs,
            'profiles' => $profiles,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $name = trim((string)($data['name'] ?? ''));
        $profileId = (int)($data['profile_id'] ?? 0);
        $cron = trim((string)($data['cron_expr'] ?? '* * * * *'));
        $profile = $this->profiles->findById($profileId);
        if ($name === '' || !$profile) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Name and profile are required.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        if ($cron === '') {
            $cron = '* * * * *';
        }
        $id = $this->jobs->create((int)$session['user_id'], $profileId, $name, $cron);
        $this->audit->recordFromSession($session, 'job_scheduler.create', 'scheduled_job', [
            'job_id' => $id,
            'profile_code' => $profile['code'],
            'cron' => $cron,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Job ' . $name . ' scheduled.'];
        return $response->withHeader('Location', '/jobs')->withStatus(302);
    }

    public function transition(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $newState = (string)($data['state'] ?? '');
        $allowed = ['queued', 'paused', 'pending', 'deleted'];
        if (!in_array($newState, $allowed, true)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Invalid target state.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        $job = $this->jobs->findById($id);
        if (!$job) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown job.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        if ($session['role'] !== 'admin' && (int)$job['owner_id'] !== (int)$session['user_id']) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You may not modify this job.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        if (!$this->jobs->transition($id, $newState)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Invalid transition to ' . $newState . '.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        $this->audit->recordFromSession($session, 'job_scheduler.transition', 'scheduled_job', [
            'job_id' => $id,
            'state' => $newState,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Job moved to ' . $newState . '.'];
        return $response->withHeader('Location', '/jobs')->withStatus(302);
    }

    public function run(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $job = $this->jobs->findById($id);
        if (!$job) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown job.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        if ($session['role'] !== 'admin' && (int)$job['owner_id'] !== (int)$session['user_id']) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You may not run this job.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        if (!$this->jobs->transition($id, 'running')) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Cannot run from current state.'];
            return $response->withHeader('Location', '/jobs')->withStatus(302);
        }
        $runId = $this->runs->start($id);
        $start = microtime(true);
        $profile = $this->profiles->findById((int)$job['profile_id']);
        $output = 'Executed profile ' . ($profile['code'] ?? 'unknown') . ' (' . $profile['name'] ?? '' . ')' . PHP_EOL;
        $output .= 'Command: ' . ($profile['command'] ?? '') . PHP_EOL;
        $output .= 'Started: ' . (new \DateTimeImmutable('now'))->format('c') . PHP_EOL;
        $output .= 'Completed: ' . (new \DateTimeImmutable('now'))->format('c') . PHP_EOL;
        $duration = (int)round((microtime(true) - $start) * 1000);
        $this->runs->finish($runId, 'success', 0, $output, '', $duration);
        $this->jobs->transition($id, 'completed');
        $this->jobs->markRun($id, (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
        $this->audit->recordFromSession($session, 'job_scheduler.run', 'scheduled_job', [
            'job_id' => $id,
            'run_id' => $runId,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Run ' . $runId . ' completed.'];
        return $response->withHeader('Location', '/job_runs/' . $id)->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $jobs = $this->jobs->all($session['role'] === 'admin' ? null : (int)$session['user_id']);
        $payload = json_encode(['jobs' => $jobs, 'profiles' => $this->profiles->all()]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiCreate(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $data = (array)$request->getParsedBody();
        $name = (string)($data['name'] ?? '');
        $profileId = (int)($data['profile_id'] ?? 0);
        if ($name === '' || !$this->profiles->findById($profileId)) {
            $payload = json_encode(['ok' => false, 'error' => 'name and profile_id are required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $id = $this->jobs->create((int)$session['user_id'], $profileId, $name, (string)($data['cron_expr'] ?? '* * * * *'));
        $this->audit->recordFromSession($session, 'job_scheduler.create_api', 'scheduled_job', ['job_id' => $id]);
        $payload = json_encode(['ok' => true, 'id' => $id]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
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
        $job = $this->jobs->findById($id);
        if (!$job) {
            $payload = json_encode(['ok' => false, 'error' => 'Unknown job.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$job['owner_id'] !== (int)$session['user_id']) {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        $newState = (string)($data['state'] ?? '');
        $allowed = ['queued', 'paused', 'pending', 'deleted'];
        if (!in_array($newState, $allowed, true)) {
            $payload = json_encode(['ok' => false, 'error' => 'Invalid state.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if (!$this->jobs->transition($id, $newState)) {
            $payload = json_encode(['ok' => false, 'error' => 'Invalid transition to ' . $newState]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->audit->recordFromSession($session, 'job_scheduler.update_api', 'scheduled_job', ['job_id' => $id, 'state' => $newState]);
        $payload = json_encode(['ok' => true, 'state' => $newState]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}