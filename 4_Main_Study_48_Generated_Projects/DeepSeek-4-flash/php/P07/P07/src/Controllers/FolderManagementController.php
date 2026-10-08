<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class FolderManagementController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        return $this->view->render($response, 'folders.php', ['current_user' => $user]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $folders = $this->files->listFolders((int) $user['id']);
        foreach ($folders as &$folder) {
            $folder['file_count'] = (int) $this->db->value('SELECT COUNT(*) FROM files WHERE folder_id = ? AND status = \'active\'', [(int) $folder['id']]);
            $folder['subfolder_count'] = (int) $this->db->value('SELECT COUNT(*) FROM folders WHERE parent_id = ?', [(int) $folder['id']]);
        }
        unset($folder);
        return $this->json($response, ['ok' => true, 'folders' => $folders]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $name = trim((string) ($data['name'] ?? ''));
        $parentId = (int) ($data['parent_id'] ?? 0);
        $action = (string) ($data['action'] ?? 'create');

        if ($name === '') {
            return $this->json($response, ['ok' => false, 'errors' => ['Folder name is required.']], 422);
        }
        if ($action === 'delete') {
            $folderId = (int) ($data['folder_id'] ?? 0);
            $result = $this->files->deleteFolder($folderId, (int) $user['id']);
            if (!$result['ok']) {
                return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
            }
            $this->audit->log((int) $user['id'], 'folder.deleted', 'folder', (string) $folderId, []);
            return $this->json($response, ['ok' => true, 'message' => 'Folder deleted.']);
        }
        if ($parentId > 0 && !$this->files->findFolder($parentId, (int) $user['id'])) {
            return $this->json($response, ['ok' => false, 'errors' => ['Parent folder not found or not owned by you.']], 404);
        }
        $result = $this->files->createFolder((int) $user['id'], $parentId > 0 ? $parentId : null, $name);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'folder.created', 'folder', (string) $result['id'], ['name' => $name]);
        return $this->json($response, ['ok' => true, 'id' => $result['id'], 'message' => 'Folder created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $folderId = (int) $args['id'];
        $action = (string) ($data['action'] ?? 'rename');
        if ($action === 'move') {
            $target = (int) ($data['target_parent_id'] ?? 0);
            $result = $this->files->moveFolder($folderId, (int) $user['id'], $target);
            if (!$result['ok']) {
                return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
            }
            $this->audit->log((int) $user['id'], 'folder.moved', 'folder', (string) $folderId, ['target' => $target]);
            return $this->json($response, ['ok' => true, 'message' => 'Folder moved.']);
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->json($response, ['ok' => false, 'errors' => ['New folder name is required.']], 422);
        }
        $result = $this->files->renameFolder($folderId, (int) $user['id'], $name);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'folder.renamed', 'folder', (string) $folderId, ['name' => $name]);
        return $this->json($response, ['ok' => true, 'message' => 'Folder renamed.']);
    }
}
