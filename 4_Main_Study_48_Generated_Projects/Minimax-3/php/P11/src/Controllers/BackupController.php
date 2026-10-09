<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BackupRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\UploadedFile;

final class BackupController
{
    public function __construct(private BackupRepository $backups) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $user['role'] === 'admin' ? $this->backups->listAll() : $this->backups->listForUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'backups', ['user' => $user, 'items' => $items, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $scope = trim((string)($body['scope'] ?? 'site'));
        $targetId = isset($body['target_id']) && $body['target_id'] !== '' ? (int)$body['target_id'] : null;
        $note = trim((string)($body['note'] ?? ''));
        $filename = 'backup_' . $scope . '_' . date('Ymd_His') . '.tar.gz';

        if (!Validator::oneOf($scope, ['site','database','full'])) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_scope'], 422);
            Flash::set('error', 'Invalid backup scope'); return View::redirect($response, '/backups?error=scope');
        }

        // write a placeholder tar.gz marker so the download endpoint has content
        $backupDir = dirname(__DIR__, 2) . '/storage/backups/' . (int)$user['id'];
        if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);
        $target = $backupDir . '/' . $filename;
        file_put_contents($target, "BACKUP " . $scope . " target=" . ($targetId ?? '-') . " at " . date('c'));
        $size = filesize($target) ?: 0;

        $id = $this->backups->create((int)$user['id'], $scope, $targetId, $filename, $size, $note ?: null);
        AuditLogger::log((int)$user['id'], $user['role'], 'backup.create', 'backup', $id, "$scope ($filename)", $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Backup $filename created");
        return View::redirect($response, '/backups');
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $uploaded = ($request->getUploadedFiles()['file'] ?? null);
        if (!$uploaded instanceof UploadedFile || $uploaded->getError() !== UPLOAD_ERR_OK) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'upload_failed'], 422);
            Flash::set('error', 'Upload failed'); return View::redirect($response, '/backups?error=upload');
        }
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $uploaded->getClientFilename() ?: 'upload.tar.gz');
        if (!str_ends_with($name, '.tar.gz') && !str_ends_with($name, '.zip')) {
            return View::json($response, ['error' => 'invalid_type'], 422);
        }
        if ($this->backups->existsForUser((int)$user['id'], $name)) {
            return View::json($response, ['error' => 'duplicate'], 409);
        }
        $size = (int)$uploaded->getSize();
        if ($size > 100 * 1024 * 1024) return View::json($response, ['error' => 'too_large'], 413);

        $backupDir = dirname(__DIR__, 2) . '/storage/backups/' . (int)$user['id'];
        if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);
        $target = $backupDir . '/' . bin2hex(random_bytes(4)) . '_' . $name;
        $uploaded->moveTo($target);

        $id = $this->backups->create((int)$user['id'], 'full', null, $name, $size, 'uploaded');
        AuditLogger::log((int)$user['id'], $user['role'], 'backup.upload', 'backup', $id, $name, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Backup $name uploaded");
        return View::redirect($response, '/backups');
    }

    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $bk = $this->backups->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$bk) return View::json($response, ['error' => 'not_found'], 404);
        $this->backups->markRestored((int)$bk['id'], (int)$user['id'], $user['role']);
        AuditLogger::log((int)$user['id'], $user['role'], 'backup.restore', 'backup', (int)$bk['id'], $bk['filename'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', "Backup {$bk['filename']} restored");
        return View::redirect($response, '/backups');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $bk = $this->backups->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$bk) return View::json($response, ['error' => 'not_found'], 404);
        $this->backups->delete((int)$bk['id'], (int)$user['id'], $user['role']);
        AuditLogger::log((int)$user['id'], $user['role'], 'backup.delete', 'backup', (int)$bk['id'], $bk['filename'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'Backup removed'); return View::redirect($response, '/backups');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}