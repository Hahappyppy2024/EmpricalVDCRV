<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ResourceRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ResourceController
{
    public function __construct(private ResourceRepository $resources) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $summary = $this->resources->summary((int)$user['id']);
        $series = $this->resources->forUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['summary' => $summary, 'series' => $series]);
        return View::render($response, 'resources', ['user' => $user, 'summary' => $summary, 'series' => $series, 'flash' => Flash::pull()]);
    }

    public function record(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $period = trim((string)($body['period'] ?? date('Y-m-d')));
        $cpu = (float)($body['cpu_percent'] ?? 0);
        $disk = (int)($body['disk_used_mb'] ?? 0);
        $bw = (int)($body['bandwidth_used_mb'] ?? 0);
        $emails = (int)($body['emails_sent'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) return View::json($response, ['error' => 'invalid_period'], 422);
        $this->resources->record((int)$user['id'], $period, $cpu, $disk, $bw, $emails);
        AuditLogger::log((int)$user['id'], $user['role'], 'resource.report', 'resource', null, "period=$period cpu=$cpu", $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok'], 201);
        Flash::set('success', 'Usage report recorded');
        return View::redirect($response, '/resources');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $row = $this->resources->findOwned((int)$args['id'], (int)$user['id']);
        if (!$row) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $cpu = (float)($body['cpu_percent'] ?? $row['cpu_percent']);
        $disk = (int)($body['disk_used_mb'] ?? $row['disk_used_mb']);
        $bw = (int)($body['bandwidth_used_mb'] ?? $row['bandwidth_used_mb']);
        $emails = (int)($body['emails_sent'] ?? $row['emails_sent']);
        $this->resources->updateReport((int)$row['id'], (int)$user['id'], $cpu, $disk, $bw, $emails);
        AuditLogger::log((int)$user['id'], $user['role'], 'resource.update', 'resource', (int)$row['id'], null, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}