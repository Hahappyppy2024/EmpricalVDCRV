<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Repositories\SettingsRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\QuotaService;
use CloudFS\Services\RealtimeService;
use CloudFS\Services\StorageService;
use CloudFS\Services\ValidationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class FileUploadController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private StorageService $storage,
        private QuotaService $quota,
        private SettingsRepository $settings,
        private ValidationService $validation,
        private PhpRenderer $view,
        private AuditService $audit,
        private RealtimeService $realtime
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $folders = $this->files->listFolders((int) $user['id']);
        return $this->view->render($response, 'upload.php', ['current_user' => $user, 'folders' => $folders, 'blocked' => $this->settings->blockedExtensions()]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $files = $this->files->listForOwner((int) $user['id']);
        return $this->json($response, ['ok' => true, 'files' => $files, 'quota' => $this->quota->summary((int) $user['id'])]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $userId = (int) $user['id'];

        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        $folderId = (int) ($request->getParsedBody()['folder_id'] ?? 0);
        $description = trim((string) ($request->getParsedBody()['description'] ?? ''));
        $tags = trim((string) ($request->getParsedBody()['tags'] ?? ''));
        $name = trim((string) ($request->getParsedBody()['name'] ?? ''));

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['ok' => false, 'errors' => ['No valid file was uploaded.']], 422);
        }
        $size = $file->getSize();
        $originalName = $name !== '' ? $name : $file->getClientFilename();
        $maxUpload = (int) $this->settings->get('max_upload_bytes', '52428800');
        if ($size > $maxUpload) {
            return $this->json($response, ['ok' => false, 'errors' => ['File exceeds the maximum upload size.']], 422);
        }
        if (!$this->validation->extensionAllowed((string) $originalName, array_keys($this->settings->blockedExtensions()))) {
            return $this->json($response, ['ok' => false, 'errors' => ['This file type is blocked by the storage policy.']], 422);
        }
        if (!$this->quota->canAccommodate($userId, $size)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Upload rejected: storage quota would be exceeded.']], 422);
        }
        if ($folderId > 0 && !$this->files->findFolder($folderId, $userId)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Target folder not found or not owned by you.']], 404);
        }

        $tmpPath = $file->getStream()->getMetadata('uri');
        $stored = $this->storage->storeFromUpload($tmpPath, (string) $originalName);
        $mime = $this->mimeFor($originalName);

        $fileId = $this->files->createFile([
            'owner_id' => $userId,
            'folder_id' => $folderId > 0 ? $folderId : null,
            'name' => $originalName,
            'original_name' => $originalName,
            'storage_path' => $stored['storage_path'],
            'mime_type' => $mime,
            'size_bytes' => $stored['size_bytes'],
            'description' => $description,
            'tags' => $tags,
        ]);
        $this->files->addVersion($fileId, 1, $stored['storage_path'], $stored['size_bytes'], $mime, $userId, 'Initial upload');
        $this->quota->recompute($userId);
        $this->audit->log($userId, 'file.uploaded', 'file', (string) $fileId, ['name' => $originalName, 'size' => $stored['size_bytes']]);
        $this->realtime->publish('file.uploaded', ['user' => $user['username'], 'file' => $originalName]);

        return $this->json($response, [
            'ok' => true,
            'id' => $fileId,
            'name' => $originalName,
            'size_bytes' => $stored['size_bytes'],
            'message' => 'File uploaded successfully.',
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $file = $this->files->findFile((int) $args['id']);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $data = $this->parseBody($request);
        $fields = [];
        if (isset($data['description'])) {
            $fields['description'] = trim((string) $data['description']);
        }
        if (isset($data['tags'])) {
            $fields['tags'] = trim((string) $data['tags']);
        }
        if (isset($data['name']) && trim((string) $data['name']) !== '') {
            $fields['name'] = trim((string) $data['name']);
        }
        if (!$fields) {
            return $this->json($response, ['ok' => false, 'errors' => ['Nothing to update.']], 422);
        }
        $this->files->updateFile((int) $args['id'], $fields);
        $this->audit->log((int) $user['id'], 'file.metadata.updated', 'file', (string) $args['id'], ['fields' => array_keys($fields)]);
        return $this->json($response, ['ok' => true, 'id' => (int) $args['id'], 'message' => 'File metadata updated.']);
    }

    private function mimeFor(string $name): string
    {
        $map = [
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }
}
