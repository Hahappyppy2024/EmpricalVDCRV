<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\ContactRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class ContactManagementController
{
    public function __construct(
        private ContactRepository $contacts,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $q = isset($params['q']) ? trim((string) $params['q']) : null;
        $items = $this->contacts->listFor((int) $user['id'], $q);
        return $this->view->render($response, 'contacts.php', [
            'user' => $user,
            'items' => $items,
            'q' => $q,
            'flash' => null,
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $org = trim((string) ($data['organization'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name required';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email required';
        }
        $items = $this->contacts->listFor((int) $user['id']);
        if ($errors) {
            return $this->view->render($response->withStatus(422), 'contacts.php', [
                'user' => $user,
                'items' => $items,
                'q' => null,
                'flash' => ['ok' => false, 'msg' => implode('; ', $errors)],
            ]);
        }
        try {
            $id = $this->contacts->create((int) $user['id'], [
                'name' => $name,
                'email' => $email,
                'organization' => $org ?: null,
                'phone' => $phone ?: null,
                'notes' => $notes ?: null,
            ]);
        } catch (\PDOException $e) {
            return $this->view->render($response->withStatus(409), 'contacts.php', [
                'user' => $user,
                'items' => $items,
                'q' => null,
                'flash' => ['ok' => false, 'msg' => 'Duplicate contact email.'],
            ]);
        }
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'contact.create', 'contact', (string) $id, $email, $ip);
        return ResponseHelper::redirect($response, '/mail/contacts?created=1');
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $contact = $this->contacts->find((int) $user['id'], $id);
        if (!$contact) {
            return ResponseHelper::redirect($response, '/mail/contacts');
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseHelper::redirect($response, '/mail/contacts?error=1');
        }
        $this->contacts->update((int) $user['id'], $id, [
            'name' => $name,
            'email' => $email,
            'organization' => $data['organization'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'contact.update', 'contact', (string) $id, $email, $ip);
        return ResponseHelper::redirect($response, '/mail/contacts?updated=1');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $contact = $this->contacts->find((int) $user['id'], $id);
        if ($contact) {
            $this->contacts->delete((int) $user['id'], $id);
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->session->recordAudit((int) $user['id'], $user['role'], 'contact.delete', 'contact', (string) $id, $contact['email'], $ip);
        }
        return ResponseHelper::redirect($response, '/mail/contacts?deleted=1');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $q = isset($params['q']) ? trim((string) $params['q']) : null;
        return ResponseHelper::json($response, ['contacts' => $this->contacts->listFor((int) $user['id'], $q)]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseHelper::validationError($response, ['name' => 'required', 'email' => 'invalid']);
        }
        try {
            $id = $this->contacts->create((int) $user['id'], [
                'name' => $name,
                'email' => $email,
                'organization' => $data['organization'] ?? null,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\PDOException $e) {
            return ResponseHelper::stableError($response, 'duplicate_email', 409);
        }
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'contact.create.api', 'contact', (string) $id, $email, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $contact = $this->contacts->find((int) $user['id'], $id);
        if (!$contact) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? $contact['name']));
        $email = trim((string) ($data['email'] ?? $contact['email']));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseHelper::validationError($response, ['name' => 'required', 'email' => 'invalid']);
        }
        $this->contacts->update((int) $user['id'], $id, [
            'name' => $name,
            'email' => $email,
            'organization' => $data['organization'] ?? $contact['organization'],
            'phone' => $data['phone'] ?? $contact['phone'],
            'notes' => $data['notes'] ?? $contact['notes'],
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'contact.update.api', 'contact', (string) $id, $email, $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}