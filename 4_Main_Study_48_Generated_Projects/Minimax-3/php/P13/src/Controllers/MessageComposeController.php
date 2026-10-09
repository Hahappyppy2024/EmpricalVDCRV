<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\ContactRepository;
use MailServer\Repositories\MessageRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class MessageComposeController
{
    public function __construct(
        private MessageRepository $messages,
        private ContactRepository $contacts,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $to = (string) ($params['to'] ?? '');
        $subject = (string) ($params['subject'] ?? '');
        $drafts = $this->messages->draftsFor((int) $user['id']);
        return $this->view->render($response, 'compose.php', [
            'user' => $user,
            'drafts' => $drafts,
            'prefill' => ['to' => $to, 'subject' => $subject],
            'result' => null,
        ]);
    }

    public function send(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $to = trim((string) ($data['to_addresses'] ?? ''));
        $cc = trim((string) ($data['cc_addresses'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body_text'] ?? ''));
        $action = (string) ($data['action'] ?? 'send');

        $errors = [];
        if ($to === '') {
            $errors['to_addresses'] = 'required';
        } else {
            foreach (preg_split('/[,\s]+/', $to) as $addr) {
                if ($addr === '') {
                    continue;
                }
                if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $errors['to_addresses'] = 'invalid_recipient:' . $addr;
                    break;
                }
            }
        }
        if ($subject === '') {
            $errors['subject'] = 'required';
        }
        if ($body === '') {
            $errors['body_text'] = 'required';
        }
        if ($errors) {
            $drafts = $this->messages->draftsFor((int) $user['id']);
            return $this->view->render($response->withStatus(422), 'compose.php', [
                'user' => $user,
                'drafts' => $drafts,
                'prefill' => ['to' => $to, 'subject' => $subject, 'body_text' => $body],
                'result' => ['ok' => false, 'errors' => $errors],
            ]);
        }

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($action === 'save_draft') {
            $id = $this->messages->saveDraft((int) $user['id'], [
                'to_addresses' => $to,
                'cc_addresses' => $cc ?: null,
                'subject' => $subject,
                'body_text' => $body,
                'body_html' => null,
            ]);
            $this->session->recordAudit((int) $user['id'], $user['role'], 'message.draft.save', 'draft', (string) $id, 'draft saved', $ip);
            $drafts = $this->messages->draftsFor((int) $user['id']);
            return $this->view->render($response, 'compose.php', [
                'user' => $user,
                'drafts' => $drafts,
                'prefill' => ['to' => '', 'subject' => ''],
                'result' => ['ok' => true, 'state' => 'draft_saved', 'id' => $id],
            ]);
        }

        $id = $this->messages->send((int) $user['id'], [
            'from_address' => $user['email'],
            'from_name' => $user['full_name'],
            'to_addresses' => $to,
            'cc_addresses' => $cc ?: null,
            'subject' => $subject,
            'body_text' => $body,
            'body_html' => null,
        ]);
        $this->session->recordAudit((int) $user['id'], $user['role'], 'message.send', 'message', (string) $id, 'sent', $ip);
        return ResponseHelper::redirect($response, '/mail/message/' . $id . '?sent=1');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $drafts = $this->messages->draftsFor((int) $user['id']);
        return ResponseHelper::json($response, ['drafts' => $drafts, 'role' => $user['role']]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $to = trim((string) ($data['to_addresses'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body_text'] ?? ''));

        $errors = [];
        if ($to === '') {
            $errors['to_addresses'] = 'required';
        } else {
            foreach (preg_split('/[,\s]+/', $to) as $addr) {
                if ($addr === '') {
                    continue;
                }
                if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $errors['to_addresses'] = 'invalid_recipient:' . $addr;
                    break;
                }
            }
        }
        if ($subject === '') {
            $errors['subject'] = 'required';
        }
        if ($body === '') {
            $errors['body_text'] = 'required';
        }
        if ($errors) {
            return ResponseHelper::validationError($response, $errors);
        }

        $id = $this->messages->send((int) $user['id'], [
            'from_address' => $user['email'],
            'from_name' => $user['full_name'],
            'to_addresses' => $to,
            'cc_addresses' => $data['cc_addresses'] ?? null,
            'subject' => $subject,
            'body_text' => $body,
            'body_html' => $data['body_html'] ?? null,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'message.send.api', 'message', (string) $id, 'api sent', $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id, 'status' => 'sent'], 201);
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
        $newSubject = trim((string) ($data['subject'] ?? $msg['subject']));
        $pdo = \MailServer\Database\Database::connection();
        $pdo->prepare('UPDATE messages SET subject = ? WHERE id = ? AND user_id = ?')->execute([$newSubject, $id, $user['id']]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'message.update', 'message', (string) $id, 'subject updated', $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}