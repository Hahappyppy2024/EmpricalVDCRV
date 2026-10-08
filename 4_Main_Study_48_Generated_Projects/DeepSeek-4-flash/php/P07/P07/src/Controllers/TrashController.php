<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\StorageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class TrashController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private StorageService $storage,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'trash.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        return $this->json($response, ['ok' => true, 'items' => $this->files->listTrash((int) $user['id'])]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fileId = (int) ($data['file_id'] ?? 0);
        $file = $this->files->findFile($fileId);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id'] || $file['status'] !== 'active') {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $this->files->trashFile($fileId, $file['folder_id'] !== null ? (int) $file['folder_id'] : 0, (int) $user['id']);
        $this->audit->log((int) $user['id'], 'trash.file.deleted', 'file', (string) $fileId, ['action' => 'to_trash']);
        return $this->json($response, ['ok' => true, 'message' => 'File moved to trash.']);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $trashId = (int) $args['id'];
        $action = (string) ($data['action'] ?? 'restore');

        if ($action === 'purge') {
            $entry = $this->files->purgeFile($trashId, (int) $user['id']);
            if (!$entry) {
                return $this->json($response, ['ok' => false, 'errors' => ['Trash item not found or out of scope.']], 404);
            }
            $this->storage->delete($entry['storage_path']);
            $this->audit->log((int) $user['id'], 'trash.purged', 'file', (string) $entry['file_id'], []);
            return $this->json($response, ['ok' => true, 'message' => 'File permanently deleted.']);
        }

        $entry = $this->files->trashEntry($trashId, (int) $user['id']);
        if (!$entry) {
            return $this->json($response, ['ok' => false, 'errors' => ['Trash item not found or out of scope.']], 404);
        }
        $this->files->restoreFile($trashId);
        $this->audit->log((int) $user['id'], 'trash.restored', 'file', (string) $entry['file_id'], []);
        return $this->json($response, ['ok' => true, 'message' => 'File restored from trash.']);
    }
}
