<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\MailboxRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class MailboxOverviewController
{
    public function __construct(
        private MailboxRepository $mailbox,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $folderId = isset($params['folder']) ? (int) $params['folder'] : null;
        $search = isset($params['q']) ? trim((string) $params['q']) : null;

        $folders = $this->mailbox->foldersForUser((int) $user['id']);
        if ($folderId === null && $folders) {
            foreach ($folders as $f) {
                if ($f['folder_type'] === 'inbox') {
                    $folderId = (int) $f['id'];
                    break;
                }
            }
        }
        $currentFolder = null;
        foreach ($folders as $f) {
            if ((int) $f['id'] === $folderId) {
                $currentFolder = $f;
                break;
            }
        }

        $messages = [];
        if ($search) {
            $messages = $this->mailbox->search((int) $user['id'], $search);
        } elseif ($currentFolder) {
            $messages = $this->mailbox->messagesInFolder((int) $user['id'], (int) $currentFolder['id']);
        }

        return $this->view->render($response, 'mailbox.php', [
            'user' => $user,
            'folders' => $folders,
            'currentFolder' => $currentFolder,
            'messages' => $messages,
            'search' => $search,
        ]);
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $folderId = isset($params['folder']) ? (int) $params['folder'] : null;
        $search = isset($params['q']) ? trim((string) $params['q']) : null;
        $folders = $this->mailbox->foldersForUser((int) $user['id']);

        $messages = [];
        $error = null;
        $errorState = null;
        if ($search) {
            $messages = $this->mailbox->search((int) $user['id'], $search);
        } elseif ($folderId !== null) {
            $folder = $this->mailbox->folderById((int) $user['id'], $folderId);
            if (!$folder) {
                return ResponseHelper::stableError($response, 'unknown_folder', 404);
            }
            $messages = $this->mailbox->messagesInFolder((int) $user['id'], $folderId);
        } else {
            $errorState = 'missing_folder_or_query';
            $error = 'Specify folder or q parameter.';
        }

        return ResponseHelper::json($response, [
            'folders' => $folders,
            'current_folder' => $folderId,
            'messages' => $messages,
            'search' => $search,
            'error' => $error,
            'error_state' => $errorState,
        ]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $type = trim((string) ($data['folder_type'] ?? 'custom'));
        if ($name === '') {
            return ResponseHelper::validationError($response, ['name' => 'required']);
        }
        $id = $this->mailbox->createFolder((int) $user['id'], $name, $type);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id]);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $folderId = (int) ($args['id'] ?? 0);
        $folder = $this->mailbox->folderById((int) $user['id'], $folderId);
        if (!$folder) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ResponseHelper::validationError($response, ['name' => 'required']);
        }
        $pdo = \MailServer\Database\Database::connection();
        $pdo->prepare('UPDATE folders SET name = ? WHERE id = ? AND user_id = ?')->execute([$name, $folderId, $user['id']]);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}