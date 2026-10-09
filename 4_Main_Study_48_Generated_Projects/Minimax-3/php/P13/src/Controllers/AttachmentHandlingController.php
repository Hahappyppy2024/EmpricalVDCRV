<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\FileRepository;
use MailServer\Repositories\MessageRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Slim\Views\PhpRenderer;

class AttachmentHandlingController
{
    private const MAX_BYTES = 25 * 1024 * 1024;
    private const ALLOWED = [
        'application/pdf', 'text/plain', 'text/csv',
        'image/png', 'image/jpeg', 'image/gif',
        'application/zip', 'application/json',
        'application/octet-stream',
    ];

    public function __construct(
        private FileRepository $files,
        private MessageRepository $messages,
        private SessionService $session,
        private PhpRenderer $view,
        private string $uploadDir
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->files->listFor((int) $user['id']);
        return $this->view->render($response, 'attachments.php', [
            'user' => $user,
            'items' => $items,
            'result' => null,
            'error' => null,
        ]);
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        $items = $this->files->listFor((int) $user['id']);
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->view->render($response->withStatus(422), 'attachments.php', [
                'user' => $user,
                'items' => $items,
                'result' => null,
                'error' => 'Please choose a file to upload.',
            ]);
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->view->render($response->withStatus(400), 'attachments.php', [
                'user' => $user,
                'items' => $items,
                'result' => null,
                'error' => 'Upload failed with error code ' . $file->getError() . '.',
            ]);
        }
        $size = $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return $this->view->render($response->withStatus(413), 'attachments.php', [
                'user' => $user,
                'items' => $items,
                'result' => null,
                'error' => 'File too large or empty. Maximum size is 25 MB.',
            ]);
        }
        $mime = $file->getClientMediaType() ?: 'application/octet-stream';
        if (!in_array($mime, self::ALLOWED, true)) {
            return $this->view->render($response->withStatus(415), 'attachments.php', [
                'user' => $user,
                'items' => $items,
                'result' => null,
                'error' => 'File type not allowed: ' . $mime,
            ]);
        }
        $origName = $file->getClientFilename() ?: 'upload.bin';
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
        $stored = bin2hex(random_bytes(8)) . '_' . $safe;
        $path = rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
        $file->moveTo($path);
        $id = $this->files->store((int) $user['id'], $origName, $mime, $size, $stored);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'attachment.upload', 'attachment', (string) $id, $origName . ' (' . $size . ' bytes)', $ip);
        $items = $this->files->listFor((int) $user['id']);
        return $this->view->render($response, 'attachments.php', [
            'user' => $user,
            'items' => $items,
            'result' => ['ok' => true, 'id' => $id, 'filename' => $origName, 'size' => $size],
            'error' => null,
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $att = $this->files->find($id);
        if (!$att || (int) $att['user_id'] !== (int) $user['id']) {
            return $this->view->render($response->withStatus(404), 'attachments.php', [
                'user' => $user,
                'items' => $this->files->listFor((int) $user['id']),
                'result' => null,
                'error' => 'Attachment not found.',
            ]);
        }
        $path = rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $att['storage_path'];
        if (!is_file($path)) {
            return $this->view->render($response->withStatus(410), 'attachments.php', [
                'user' => $user,
                'items' => $this->files->listFor((int) $user['id']),
                'result' => null,
                'error' => 'File missing on storage.',
            ]);
        }
        $stream = fopen($path, 'rb');
        $body = new \Slim\Psr7\Stream($stream);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'attachment.download', 'attachment', (string) $id, $att['filename'], $ip);
        return $response->withBody($body)
            ->withHeader('Content-Type', $att['mime_type'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $att['filename'] . '"');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->files->listFor((int) $user['id']);
        return ResponseHelper::json($response, ['attachments' => $items]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ResponseHelper::validationError($response, ['file' => 'required']);
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ResponseHelper::stableError($response, 'upload_failed', 400);
        }
        $size = $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ResponseHelper::stableError($response, 'payload_too_large', 413);
        }
        $mime = $file->getClientMediaType() ?: 'application/octet-stream';
        if (!in_array($mime, self::ALLOWED, true)) {
            return ResponseHelper::stableError($response, 'unsupported_media_type', 415);
        }
        $origName = $file->getClientFilename() ?: 'upload.bin';
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
        $stored = bin2hex(random_bytes(8)) . '_' . $safe;
        $path = rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
        $file->moveTo($path);
        $id = $this->files->store((int) $user['id'], $origName, $mime, $size, $stored);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'attachment.upload.api', 'attachment', (string) $id, $origName, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id, 'filename' => $origName, 'size' => $size, 'mime' => $mime], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $att = $this->files->find($id);
        if (!$att || (int) $att['user_id'] !== (int) $user['id']) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $action = (string) ($data['action'] ?? '');
        if ($action !== 'delete') {
            return ResponseHelper::validationError($response, ['action' => 'unknown']);
        }
        $this->files->delete((int) $user['id'], $id);
        $path = rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $att['storage_path'];
        if (is_file($path)) {
            @unlink($path);
        }
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'attachment.delete', 'attachment', (string) $id, $att['filename'], $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}