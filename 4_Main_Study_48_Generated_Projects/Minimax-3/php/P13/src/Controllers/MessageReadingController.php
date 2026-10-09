<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\FileRepository;
use MailServer\Repositories\MessageRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class MessageReadingController
{
    public function __construct(
        private MessageRepository $messages,
        private FileRepository $files,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $msg = $this->messages->findById((int) $user['id'], $id);
        if (!$msg) {
            return $this->view->render($response->withStatus(404), 'message.php', [
                'user' => $user,
                'message' => null,
                'attachments' => [],
                'error' => 'Message not found.',
            ]);
        }
        if (!$msg['is_read']) {
            $this->messages->markRead((int) $user['id'], $id);
        }
        $attachments = $this->files->forMessage($id);
        $params = $request->getQueryParams();
        $flash = !empty($params['sent']) ? 'Message sent successfully.' : null;
        return $this->view->render($response, 'message.php', [
            'user' => $user,
            'message' => $msg,
            'attachments' => $attachments,
            'flash' => $flash,
        ]);
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return ResponseHelper::validationError($response, ['id' => 'required']);
        }
        $msg = $this->messages->findById((int) $user['id'], $id);
        if (!$msg) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $this->messages->markRead((int) $user['id'], $id);
        $attachments = $this->files->forMessage($id);
        return ResponseHelper::json($response, ['message' => $msg, 'attachments' => $attachments]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ResponseHelper::validationError($response, ['id' => 'required']);
        }
        $msg = $this->messages->findById((int) $user['id'], $id);
        if (!$msg) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $this->messages->markRead((int) $user['id'], $id);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id, 'is_read' => 1]);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $msg = $this->messages->findById((int) $user['id'], $id);
        if (!$msg) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $action = (string) ($data['action'] ?? '');
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        switch ($action) {
            case 'star':
                $updated = $this->messages->toggleStar((int) $user['id'], $id);
                $this->session->recordAudit((int) $user['id'], $user['role'], 'message.star', 'message', (string) $id, 'toggled star', $ip);
                return ResponseHelper::json($response, ['ok' => true, 'is_starred' => (int) $updated['is_starred']]);
            case 'move':
                $folderId = (int) ($data['folder_id'] ?? 0);
                if ($folderId <= 0) {
                    return ResponseHelper::validationError($response, ['folder_id' => 'required']);
                }
                $this->messages->moveToFolder((int) $user['id'], $id, $folderId);
                $this->session->recordAudit((int) $user['id'], $user['role'], 'message.move', 'message', (string) $id, 'moved to ' . $folderId, $ip);
                return ResponseHelper::json($response, ['ok' => true]);
            case 'delete':
                $this->messages->delete((int) $user['id'], $id);
                $this->session->recordAudit((int) $user['id'], $user['role'], 'message.delete', 'message', (string) $id, 'deleted', $ip);
                return ResponseHelper::json($response, ['ok' => true]);
            default:
                return ResponseHelper::validationError($response, ['action' => 'unknown']);
        }
    }
}