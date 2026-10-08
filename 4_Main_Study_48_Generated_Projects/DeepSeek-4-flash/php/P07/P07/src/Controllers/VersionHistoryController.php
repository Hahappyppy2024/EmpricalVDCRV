<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\RealtimeService;
use CloudFS\Services\StorageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class VersionHistoryController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private StorageService $storage,
        private PhpRenderer $view,
        private AuditService $audit,
        private RealtimeService $realtime
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'versions.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $query = $request->getQueryParams();
        if (!empty($query['file_id'])) {
            $fileId = (int) $query['file_id'];
            $file = $this->files->findFile($fileId);
            if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
                return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
            }
            $versions = $this->files->versions($fileId);
            return $this->json($response, ['ok' => true, 'file' => $file, 'versions' => $versions]);
        }
        $records = $this->db->all(
            'SELECT f.id, f.name, f.current_version, f.size_bytes, f.updated_at,
                    (SELECT COUNT(*) FROM file_versions v WHERE v.file_id = f.id) AS version_count
             FROM files f WHERE f.owner_id = ? AND f.status = \'active\' ORDER BY f.name',
            [(int) $user['id']]
        );
        return $this->json($response, ['ok' => true, 'records' => $records]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fileId = (int) ($data['file_id'] ?? 0);
        $file = $this->files->findFile($fileId);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }

        $uploaded = $request->getUploadedFiles();
        $newVersion = $uploaded['file'] ?? null;
        if (!$newVersion || $newVersion->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['ok' => false, 'errors' => ['A file is required to create a new version.']], 422);
        }

        $retention = (int) $this->db->value("SELECT value FROM settings WHERE key = 'version_retention_count'") ?: 10;
        $current = (int) $file['current_version'];
        $next = $current + 1;
        $tmpPath = $newVersion->getStream()->getMetadata('uri');
        $stored = $this->storage->storeFromUpload($tmpPath, $file['original_name']);
        $this->files->addVersion($fileId, $next, $stored['storage_path'], $stored['size_bytes'], $file['mime_type'], (int) $user['id'], trim((string) ($data['comment'] ?? '')));
        $this->files->updateFile($fileId, [
            'current_version' => $next,
            'storage_path' => $stored['storage_path'],
            'size_bytes' => $stored['size_bytes'],
        ]);

        $old = $this->db->all('SELECT id FROM file_versions WHERE file_id = ? ORDER BY version_number DESC LIMIT -1 OFFSET ?', [$fileId, $retention]);
        foreach ($old as $row) {
            $this->db->run('DELETE FROM file_versions WHERE id = ?', [(int) $row['id']]);
        }
        $this->audit->log((int) $user['id'], 'version.created', 'file', (string) $fileId, ['version' => $next]);
        $this->realtime->publish('version.uploaded', ['user' => $user['username'], 'file' => $file['name'], 'version' => $next]);
        return $this->json($response, ['ok' => true, 'id' => $fileId, 'version' => $next, 'message' => 'New version created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fileId = (int) $args['id'];
        $versionNumber = (int) ($data['version'] ?? 0);
        $file = $this->files->findFile($fileId);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $version = $this->files->version($fileId, $versionNumber);
        if (!$version) {
            return $this->json($response, ['ok' => false, 'errors' => ['Requested version does not exist.']], 404);
        }
        $content = $this->storage->read($version['storage_path']);
        $newVersion = (int) $file['current_version'] + 1;
        $this->files->addVersion($fileId, $newVersion, $version['storage_path'], $version['size_bytes'], $version['mime_type'], (int) $user['id'], 'Restored from version ' . $versionNumber);
        $this->files->updateFile($fileId, [
            'current_version' => $newVersion,
            'storage_path' => $version['storage_path'],
            'size_bytes' => $version['size_bytes'],
            'mime_type' => $version['mime_type'],
        ]);
        $this->audit->log((int) $user['id'], 'version.restored', 'file', (string) $fileId, ['from_version' => $versionNumber]);
        return $this->json($response, ['ok' => true, 'message' => 'Version ' . $versionNumber . ' restored.']);
    }
}
