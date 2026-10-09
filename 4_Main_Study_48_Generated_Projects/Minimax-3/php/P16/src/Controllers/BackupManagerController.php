<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StoredFileRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-07 — Backup manager.
 */
final class BackupManagerController
{
    public function __construct(
        private SessionService $session,
        private StoredFileRepository $files,
        private AuditService $audit,
        private PhpRenderer $view,
        private string $storagePath
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $files = $this->files->all($session['role'] === 'admin' ? null : (int)$session['user_id'], 'backup');
        return $this->view->render($response, 'backup_manager.php', [
            'title' => 'Backup manager',
            'session' => $session,
            'files' => $files,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function upload(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Please choose a valid file.'];
            return $response->withHeader('Location', '/backups')->withStatus(302);
        }
        $size = (int)$file->getSize();
        if ($size <= 0 || $size > 50 * 1024 * 1024) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'File must be between 1 byte and 50 MB.'];
            return $response->withHeader('Location', '/backups')->withStatus(302);
        }
        $clientName = $file->getClientFilename() ?: 'upload.bin';
        $mime = (string)$file->getClientMediaType();
        $extension = pathinfo($clientName, PATHINFO_EXTENSION) ?: 'bin';
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($clientName, PATHINFO_FILENAME)) ?: 'upload';
        $safeBase = substr($safeBase, 0, 60);
        $storedName = $safeBase . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
        $destDir = rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;
        $file->moveTo($destPath);
        $checksum = hash_file('sha256', $destPath) ?: '';
        $id = $this->files->create([
            'owner_id'      => (int)$session['user_id'],
            'kind'          => 'backup',
            'name'          => $storedName,
            'original_name' => $clientName,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'checksum'      => $checksum,
            'storage_path'  => $destPath,
            'description'   => (string)((array)$request->getParsedBody())['description'] ?? '',
        ]);
        $this->audit->recordFromSession($session, 'backup_manager.upload', 'stored_file', [
            'file_id' => $id,
            'name' => $clientName,
            'size' => $size,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Backup uploaded.'];
        return $response->withHeader('Location', '/backups')->withStatus(302);
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
            $response->getBody()->write('File not found');
            return $response->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$file['owner_id'] !== (int)$session['user_id']) {
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }
        if (!is_file((string)$file['storage_path'])) {
            $response->getBody()->write('Missing storage');
            return $response->withStatus(410);
        }
        $this->audit->recordFromSession($session, 'backup_manager.download', 'stored_file', ['file_id' => $id]);
        $response->getBody()->write(file_get_contents((string)$file['storage_path']));
        return $response
            ->withHeader('Content-Type', (string)$file['mime_type'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename((string)$file['original_name']) . '"');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $file = $this->files->findById($id);
        if (!$file) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown file.'];
            return $response->withHeader('Location', '/backups')->withStatus(302);
        }
        if ($session['role'] !== 'admin' && (int)$file['owner_id'] !== (int)$session['user_id']) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You may not delete this file.'];
            return $response->withHeader('Location', '/backups')->withStatus(302);
        }
        $this->files->delete($id, $session['role'] === 'admin' ? null : (int)$session['user_id']);
        if (is_file((string)$file['storage_path'])) {
            @unlink((string)$file['storage_path']);
        }
        $this->audit->recordFromSession($session, 'backup_manager.delete', 'stored_file', ['file_id' => $id]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Backup deleted.'];
        return $response->withHeader('Location', '/backups')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $files = $this->files->all($session['role'] === 'admin' ? null : (int)$session['user_id'], 'backup');
        $payload = json_encode(['backups' => $files]);
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
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $payload = json_encode(['ok' => false, 'error' => 'name is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $destDir = rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'backup';
        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
        $stored = $safeName . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        $dest = $destDir . DIRECTORY_SEPARATOR . $stored;
        $payload = (string)($data['payload'] ?? 'snapshot-' . date('c'));
        file_put_contents($dest, $payload);
        $id = $this->files->create([
            'owner_id'      => (int)$session['user_id'],
            'kind'          => 'backup',
            'name'          => $stored,
            'original_name' => $name,
            'mime_type'     => 'application/octet-stream',
            'size_bytes'    => strlen($payload),
            'checksum'      => hash('sha256', $payload) ?: '',
            'storage_path'  => $dest,
            'description'   => (string)($data['description'] ?? ''),
        ]);
        $this->audit->recordFromSession($session, 'backup_manager.create_api', 'stored_file', ['file_id' => $id]);
        $payloadJson = json_encode(['ok' => true, 'id' => $id]);
        $response->getBody()->write($payloadJson);
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
        $file = $this->files->findById($id);
        if (!$file) {
            $payload = json_encode(['ok' => false, 'error' => 'Unknown file.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$file['owner_id'] !== (int)$session['user_id']) {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        if (isset($data['action']) && $data['action'] === 'restore' && is_file((string)$file['storage_path'])) {
            $this->audit->recordFromSession($session, 'backup_manager.restore_api', 'stored_file', ['file_id' => $id]);
            $payload = json_encode(['ok' => true, 'message' => 'Restore simulated', 'size' => $file['size_bytes']]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        $payload = json_encode(['ok' => false, 'error' => 'No supported action supplied.']);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
    }
}