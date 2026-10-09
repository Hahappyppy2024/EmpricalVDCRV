<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\RuleRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class FiltersAndRulesController
{
    public function __construct(
        private RuleRepository $rules,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->rules->listFor((int) $user['id']);
        return $this->view->render($response, 'rules.php', [
            'user' => $user,
            'items' => $items,
            'flash' => null,
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $conditions = trim((string) ($data['conditions'] ?? ''));
        $actions = trim((string) ($data['actions'] ?? ''));
        $priority = (int) ($data['priority'] ?? 100);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name required';
        }
        if ($conditions === '' || strpos($conditions, ':') === false) {
            $errors[] = 'Conditions must use field:value format';
        }
        if ($actions === '' || strpos($actions, ':') === false) {
            $errors[] = 'Actions must use action:value format';
        }
        if ($errors) {
            return $this->view->render($response->withStatus(422), 'rules.php', [
                'user' => $user,
                'items' => $this->rules->listFor((int) $user['id']),
                'flash' => ['ok' => false, 'msg' => implode('; ', $errors)],
            ]);
        }
        $id = $this->rules->create((int) $user['id'], [
            'name' => $name,
            'conditions' => $conditions,
            'actions' => $actions,
            'priority' => $priority,
            'enabled' => 1,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'rule.create', 'rule', (string) $id, $name, $ip);
        return ResponseHelper::redirect($response, '/mail/rules?created=1');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $rule = $this->rules->find((int) $user['id'], $id);
        if (!$rule) {
            return ResponseHelper::redirect($response, '/mail/rules');
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? $rule['name']));
        $conditions = trim((string) ($data['conditions'] ?? $rule['conditions']));
        $actions = trim((string) ($data['actions'] ?? $rule['actions']));
        $priority = (int) ($data['priority'] ?? $rule['priority']);
        $enabled = isset($data['enabled']) ? (int) (bool) $data['enabled'] : (int) $rule['enabled'];
        $this->rules->update((int) $user['id'], $id, [
            'name' => $name,
            'conditions' => $conditions,
            'actions' => $actions,
            'priority' => $priority,
            'enabled' => $enabled,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'rule.update', 'rule', (string) $id, $name, $ip);
        return ResponseHelper::redirect($response, '/mail/rules?updated=1');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $rule = $this->rules->find((int) $user['id'], $id);
        if ($rule) {
            $this->rules->delete((int) $user['id'], $id);
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->session->recordAudit((int) $user['id'], $user['role'], 'rule.delete', 'rule', (string) $id, $rule['name'], $ip);
        }
        return ResponseHelper::redirect($response, '/mail/rules?deleted=1');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        return ResponseHelper::json($response, ['rules' => $this->rules->listFor((int) $user['id'])]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $conditions = trim((string) ($data['conditions'] ?? ''));
        $actions = trim((string) ($data['actions'] ?? ''));
        if ($name === '' || $conditions === '' || $actions === '') {
            return ResponseHelper::validationError($response, ['name' => 'required', 'conditions' => 'required', 'actions' => 'required']);
        }
        $id = $this->rules->create((int) $user['id'], [
            'name' => $name,
            'conditions' => $conditions,
            'actions' => $actions,
            'priority' => (int) ($data['priority'] ?? 100),
            'enabled' => 1,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'rule.create.api', 'rule', (string) $id, $name, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $rule = $this->rules->find((int) $user['id'], $id);
        if (!$rule) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $this->rules->update((int) $user['id'], $id, [
            'name' => trim((string) ($data['name'] ?? $rule['name'])),
            'conditions' => trim((string) ($data['conditions'] ?? $rule['conditions'])),
            'actions' => trim((string) ($data['actions'] ?? $rule['actions'])),
            'priority' => (int) ($data['priority'] ?? $rule['priority']),
            'enabled' => isset($data['enabled']) ? (int) (bool) $data['enabled'] : (int) $rule['enabled'],
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'rule.update.api', 'rule', (string) $id, 'updated', $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}