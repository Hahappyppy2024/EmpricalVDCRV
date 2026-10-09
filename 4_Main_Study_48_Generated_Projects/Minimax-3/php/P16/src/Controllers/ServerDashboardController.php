<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AlertRepository;
use App\Repositories\AuditEventRepository;
use App\Repositories\HealthCheckRepository;
use App\Repositories\JobRunRepository;
use App\Repositories\MetricSnapshotRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StoredFileRepository;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-02 — Server dashboard.
 * Operators see CPU/memory/disk/uptime/service state and top-line counters.
 */
final class ServerDashboardController
{
    public function __construct(
        private SessionService $session,
        private MetricSnapshotRepository $metrics,
        private ServiceRepository $services,
        private AlertRepository $alerts,
        private HealthCheckRepository $health,
        private JobRunRepository $jobRuns,
        private StoredFileRepository $files,
        private AuditEventRepository $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $host = $this->paramOrDefault($request, 'host', 'localhost');
        $this->metrics->generateDeterministicSeries($host);
        $latest = $this->metrics->latest($host);
        $history = array_reverse($this->metrics->history($host, 24));
        $services = $this->services->all();
        $alerts = array_slice($this->alerts->all(null, 'open'), 0, 5);
        $health = $this->health->all();
        $jobRuns = array_slice($this->jobRuns->recent(8), 0, 8);
        $backup = $this->files->all($session['user_id'], 'backup');
        $audit = array_slice($this->audit->recent(6), 0, 6);

        return $this->view->render($response, 'server_dashboard.php', [
            'title' => 'Server dashboard',
            'session' => $session,
            'host' => $host,
            'latest' => $latest,
            'history' => $history,
            'services' => $services,
            'alerts' => $alerts,
            'health' => $health,
            'jobRuns' => $jobRuns,
            'backups' => $backup,
            'audit' => $audit,
            'hosts' => $this->metrics->distinctHosts() ?: ['localhost'],
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
        $host = $this->paramOrDefault($request, 'host', 'localhost');
        $this->metrics->generateDeterministicSeries($host);
        $body = json_encode([
            'host' => $host,
            'latest' => $this->metrics->latest($host),
            'history' => $this->metrics->history($host, 50),
            'services' => $this->services->all(),
        ], JSON_PRETTY_PRINT);
        $response->getBody()->write($body);
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
        $host = isset($data['host']) && is_string($data['host']) && trim($data['host']) !== ''
            ? trim((string)$data['host'])
            : 'localhost';
        $cpu = isset($data['cpu_pct']) ? (float)$data['cpu_pct'] : null;
        $mem = isset($data['memory_pct']) ? (float)$data['memory_pct'] : null;
        $disk = isset($data['disk_pct']) ? (float)$data['disk_pct'] : null;
        $uptime = isset($data['uptime_sec']) ? (int)$data['uptime_sec'] : null;
        if ($cpu === null || $mem === null || $disk === null || $uptime === null) {
            $payload = json_encode(['ok' => false, 'error' => 'cpu_pct, memory_pct, disk_pct and uptime_sec are required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $id = $this->metrics->record([
            'host' => $host,
            'cpu_pct' => $cpu,
            'memory_pct' => $mem,
            'disk_pct' => $disk,
            'uptime_sec' => $uptime,
            'load_avg' => (float)($data['load_avg'] ?? 0.0),
            'service_state' => (string)($data['service_state'] ?? 'healthy'),
        ]);
        $payload = json_encode(['ok' => true, 'id' => $id, 'host' => $host]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    private function paramOrDefault(Request $request, string $key, string $default): string
    {
        $query = $request->getQueryParams();
        if (isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '') {
            return (string)$query[$key];
        }
        return $default;
    }
}