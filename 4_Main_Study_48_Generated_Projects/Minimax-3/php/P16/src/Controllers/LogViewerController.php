<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LogEntryRepository;
use App\Repositories\LogFileRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-03 — Log viewer.
 */
final class LogViewerController
{
    public function __construct(
        private SessionService $session,
        private LogFileRepository $files,
        private LogEntryRepository $entries,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $files = $this->files->all();
        $selectedId = (int)($request->getQueryParams()['file'] ?? ($files[0]['id'] ?? 0));
        $level = (string)($request->getQueryParams()['level'] ?? '');
        $filter = (string)($request->getQueryParams()['q'] ?? '');
        $entries = [];
        $file = null;
        if ($selectedId > 0) {
            $file = $this->files->findById($selectedId);
            if ($file) {
                $entries = $this->entries->forFile($selectedId, $level !== '' ? $level : null, $filter !== '' ? $filter : null);
            }
        }
        return $this->view->render($response, 'log_viewer.php', [
            'title' => 'Log viewer',
            'session' => $session,
            'files' => $files,
            'file' => $file,
            'entries' => $entries,
            'level' => $level,
            'filter' => $filter,
        ]);
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $file = $this->files->findById($id);
        if (!$file) {
            $response->getBody()->write('Log not found');
            return $response->withStatus(404);
        }
        $entries = $this->entries->forFile($id, null, null, 500);
        $content = '';
        foreach ($entries as $e) {
            $content .= sprintf("[%s] %s %s\n", $e['ts'], strtoupper($e['level']), $e['message']);
        }
        $this->audit->recordFromSession($session, 'log_viewer.download', 'log_file', ['file_id' => $id]);
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file['name'] . '.log"');
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $query = $request->getQueryParams();
        $files = $this->files->all();
        $selectedId = (int)($query['file'] ?? ($files[0]['id'] ?? 0));
        $level = isset($query['level']) ? (string)$query['level'] : null;
        $filter = isset($query['q']) ? (string)$query['q'] : null;
        $entries = [];
        if ($selectedId > 0) {
            $entries = $this->entries->forFile($selectedId, $level, $filter);
        }
        $payload = json_encode(['files' => $files, 'entries' => $entries, 'file_id' => $selectedId]);
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
        $fileId = (int)($data['file_id'] ?? 0);
        if (!$this->files->findById($fileId)) {
            $payload = json_encode(['ok' => false, 'error' => 'Unknown log file.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $ts = (string)($data['ts'] ?? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
        $level = (string)($data['level'] ?? 'info');
        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            $payload = json_encode(['ok' => false, 'error' => 'message is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $id = $this->entries->create($fileId, $ts, $level, $message, []);
        $this->files->touch($fileId, 0);
        $this->audit->recordFromSession($session, 'log_viewer.entry_create_api', 'log_entry', ['file_id' => $fileId]);
        $payload = json_encode(['ok' => true, 'id' => $id]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}