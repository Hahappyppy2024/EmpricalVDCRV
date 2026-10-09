<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\DomainRepository;
use MailServer\Repositories\QuarantineRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class QuarantineController
{
    public function __construct(
        private QuarantineRepository $quarantine,
        private DomainRepository $domains,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['domain_admin', 'system_admin'], true)) {
            return $this->view->render($response->withStatus(403), 'quarantine.php', [
                'user' => $user, 'items' => [], 'domain' => null,
                'flash' => ['ok' => false, 'msg' => 'Only domain/system admins can review quarantine.'],
            ]);
        }
        $params = $request->getQueryParams();
        $domainId = isset($params['domain']) ? (int) $params['domain'] : (int) $user['domain_id'];
        $status = isset($params['status']) ? trim((string) $params['status']) : null;
        if ($user['role'] !== 'system_admin' && $domainId !== (int) $user['domain_id']) {
            return $this->view->render($response->withStatus(403), 'quarantine.php', [
                'user' => $user, 'items' => [], 'domain' => null,
                'flash' => ['ok' => false, 'msg' => 'Cannot view other domains.'],
            ]);
        }
        $domain = $this->domains->find($domainId);
        $items = $domain ? $this->quarantine->listForDomain($domainId, $status) : [];
        return $this->view->render($response, 'quarantine.php', [
            'user' => $user,
            'items' => $items,
            'domain' => $domain,
            'flash' => null,
        ]);
    }

    public function resolve(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['domain_admin', 'system_admin'], true)) {
            return $this->view->render($response->withStatus(403), 'quarantine.php', [
                'user' => $user, 'items' => [], 'domain' => null,
                'flash' => ['ok' => false, 'msg' => 'Only admins can resolve quarantine.'],
            ]);
        }
        $id = (int) ($args['id'] ?? 0);
        $item = $this->quarantine->find($id);
        if (!$item) {
            return ResponseHelper::redirect($response, '/mail/quarantine?error=not_found');
        }
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== (int) $item['domain_id']) {
            return $this->view->render($response->withStatus(403), 'quarantine.php', [
                'user' => $user, 'items' => [], 'domain' => null,
                'flash' => ['ok' => false, 'msg' => 'Cannot resolve this item.'],
            ]);
        }
        $data = (array) $request->getParsedBody();
        $decision = (string) ($data['decision'] ?? '');
        if (!in_array($decision, ['release', 'delete'], true)) {
            return ResponseHelper::redirect($response, '/mail/quarantine?domain=' . $item['domain_id'] . '&error=decision');
        }
        $this->quarantine->resolve($id, (int) $user['id'], $decision);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'quarantine.' . $decision, 'quarantine', (string) $id, $decision, $ip);
        return ResponseHelper::redirect($response, '/mail/quarantine?domain=' . $item['domain_id'] . '&resolved=1');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['domain_admin', 'system_admin'], true)) {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $params = $request->getQueryParams();
        $domainId = isset($params['domain']) ? (int) $params['domain'] : (int) $user['domain_id'];
        $status = isset($params['status']) ? trim((string) $params['status']) : null;
        if ($user['role'] !== 'system_admin' && $domainId !== (int) $user['domain_id']) {
            return ResponseHelper::stableError($response, 'forbidden_cross_domain', 403);
        }
        $items = $this->quarantine->listForDomain($domainId, $status);
        return ResponseHelper::json($response, ['items' => $items, 'domain_id' => $domainId]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['domain_admin', 'system_admin'], true)) {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $data = (array) $request->getParsedBody();
        $domainId = (int) ($data['domain_id'] ?? $user['domain_id']);
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== $domainId) {
            return ResponseHelper::stableError($response, 'forbidden_cross_domain', 403);
        }
        $recipient = trim((string) ($data['recipient'] ?? ''));
        $sender = trim((string) ($data['sender'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? 'unspecified'));
        if ($recipient === '' || $sender === '') {
            return ResponseHelper::validationError($response, ['recipient' => 'required', 'sender' => 'required']);
        }
        $id = $this->quarantine->create($domainId, [
            'recipient' => $recipient,
            'sender' => $sender,
            'subject' => $data['subject'] ?? null,
            'reason' => $reason,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'quarantine.create', 'quarantine', (string) $id, $sender . '->' . $recipient, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['domain_admin', 'system_admin'], true)) {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $id = (int) ($args['id'] ?? 0);
        $item = $this->quarantine->find($id);
        if (!$item) {
            return ResponseHelper::stableError($response, 'not_found', 404);
        }
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== (int) $item['domain_id']) {
            return ResponseHelper::stableError($response, 'forbidden_cross_domain', 403);
        }
        $data = (array) $request->getParsedBody();
        $decision = (string) ($data['decision'] ?? '');
        if (!in_array($decision, ['release', 'delete'], true)) {
            return ResponseHelper::validationError($response, ['decision' => 'invalid']);
        }
        $this->quarantine->resolve($id, (int) $user['id'], $decision);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'quarantine.' . $decision . '.api', 'quarantine', (string) $id, $decision, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'status' => $decision === 'release' ? 'released' : 'deleted']);
    }
}