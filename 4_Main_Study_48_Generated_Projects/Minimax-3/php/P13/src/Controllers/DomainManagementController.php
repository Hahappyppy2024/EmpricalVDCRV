<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\DomainRepository;
use MailServer\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class DomainManagementController
{
    public function __construct(
        private DomainRepository $domains,
        private UserRepository $users,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domains = $this->domains->all();
        $current = null;
        $aliases = [];
        $members = [];
        $params = $request->getQueryParams();
        $domainId = isset($params['domain']) ? (int) $params['domain'] : null;
        if ($domainId === null && $user['domain_id']) {
            $domainId = (int) $user['domain_id'];
        }
        if ($domainId !== null) {
            foreach ($domains as $d) {
                if ((int) $d['id'] === $domainId) {
                    $current = $d;
                    break;
                }
            }
            if ($current) {
                if ($user['role'] !== 'system_admin' && (int) $current['id'] !== (int) $user['domain_id']) {
                    return $this->view->render($response->withStatus(403), 'domains.php', [
                        'user' => $user,
                        'domains' => $domains,
                        'current' => null,
                        'aliases' => [],
                        'members' => [],
                        'flash' => ['ok' => false, 'msg' => 'You can only manage your own domain.'],
                    ]);
                }
                $aliases = $this->domains->aliasesFor((int) $current['id']);
                $members = $this->users->listForDomain((int) $current['id']);
            }
        }
        return $this->view->render($response, 'domains.php', [
            'user' => $user,
            'domains' => $domains,
            'current' => $current,
            'aliases' => $aliases,
            'members' => $members,
            'flash' => null,
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return $this->view->render($response->withStatus(403), 'domains.php', [
                'user' => $user, 'domains' => $this->domains->all(),
                'current' => null, 'aliases' => [], 'members' => [],
                'flash' => ['ok' => false, 'msg' => 'Only system admins may create domains.'],
            ]);
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $maxMailboxes = (int) ($data['max_mailboxes'] ?? 50);
        $maxQuota = (int) ($data['max_quota_mb'] ?? 5120);
        if ($name === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) {
            return $this->view->render($response->withStatus(422), 'domains.php', [
                'user' => $user, 'domains' => $this->domains->all(),
                'current' => null, 'aliases' => [], 'members' => [],
                'flash' => ['ok' => false, 'msg' => 'A valid domain name is required.'],
            ]);
        }
        if ($this->domains->findByName($name)) {
            return $this->view->render($response->withStatus(409), 'domains.php', [
                'user' => $user, 'domains' => $this->domains->all(),
                'current' => null, 'aliases' => [], 'members' => [],
                'flash' => ['ok' => false, 'msg' => 'Domain already exists.'],
            ]);
        }
        $id = $this->domains->create([
            'name' => $name,
            'description' => $description ?: null,
            'max_mailboxes' => $maxMailboxes,
            'max_quota_mb' => $maxQuota,
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'domain.create', 'domain', (string) $id, $name, $ip);
        return ResponseHelper::redirect($response, '/mail/domains?domain=' . $id . '&created=1');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $domain = $this->domains->find($id);
        if (!$domain) {
            return ResponseHelper::redirect($response, '/mail/domains');
        }
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== $id) {
            return $this->view->render($response->withStatus(403), 'domains.php', [
                'user' => $user, 'domains' => $this->domains->all(),
                'current' => $domain, 'aliases' => $this->domains->aliasesFor($id), 'members' => $this->users->listForDomain($id),
                'flash' => ['ok' => false, 'msg' => 'Cannot edit this domain.'],
            ]);
        }
        $data = (array) $request->getParsedBody();
        $this->domains->update($id, [
            'description' => $data['description'] ?? null,
            'max_mailboxes' => (int) ($data['max_mailboxes'] ?? 50),
            'max_quota_mb' => (int) ($data['max_quota_mb'] ?? 5120),
            'status' => $data['status'] ?? 'active',
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'domain.update', 'domain', (string) $id, 'updated', $ip);
        return ResponseHelper::redirect($response, '/mail/domains?domain=' . $id . '&updated=1');
    }

    public function createAlias(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domainId = (int) ($args['id'] ?? 0);
        $domain = $this->domains->find($domainId);
        if (!$domain) {
            return ResponseHelper::redirect($response, '/mail/domains');
        }
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== $domainId) {
            return $this->view->render($response->withStatus(403), 'domains.php', [
                'user' => $user, 'domains' => $this->domains->all(),
                'current' => $domain, 'aliases' => $this->domains->aliasesFor($domainId), 'members' => $this->users->listForDomain($domainId),
                'flash' => ['ok' => false, 'msg' => 'Cannot manage aliases on this domain.'],
            ]);
        }
        $data = (array) $request->getParsedBody();
        $source = trim((string) ($data['source'] ?? ''));
        $destination = trim((string) ($data['destination'] ?? ''));
        if ($source === '' || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            return ResponseHelper::redirect($response, '/mail/domains?domain=' . $domainId . '&error=alias');
        }
        $id = $this->domains->createAlias($domainId, $source, $destination);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'alias.create', 'alias', (string) $id, $source . '->' . $destination, $ip);
        return ResponseHelper::redirect($response, '/mail/domains?domain=' . $domainId . '&alias_created=1');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domains = $this->domains->all();
        if ($user['role'] !== 'system_admin') {
            $domains = array_values(array_filter($domains, fn($d) => (int) $d['id'] === (int) $user['domain_id']));
        }
        return ResponseHelper::json($response, ['domains' => $domains]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) {
            return ResponseHelper::validationError($response, ['name' => 'invalid']);
        }
        if ($this->domains->findByName($name)) {
            return ResponseHelper::stableError($response, 'duplicate_domain', 409);
        }
        $id = $this->domains->create([
            'name' => $name,
            'description' => $data['description'] ?? null,
            'max_mailboxes' => (int) ($data['max_mailboxes'] ?? 50),
            'max_quota_mb' => (int) ($data['max_quota_mb'] ?? 5120),
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'domain.create.api', 'domain', (string) $id, $name, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $domain = $this->domains->find($id);
        if (!$domain) {
            return ResponseHelper::stableError($response, 'not_found', 404);
        }
        if ($user['role'] !== 'system_admin' && (int) $user['domain_id'] !== $id) {
            return ResponseHelper::stableError($response, 'forbidden', 403);
        }
        $data = (array) $request->getParsedBody();
        $this->domains->update($id, [
            'description' => $data['description'] ?? $domain['description'],
            'max_mailboxes' => (int) ($data['max_mailboxes'] ?? $domain['max_mailboxes']),
            'max_quota_mb' => (int) ($data['max_quota_mb'] ?? $domain['max_quota_mb']),
            'status' => $data['status'] ?? $domain['status'],
        ]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'domain.update.api', 'domain', (string) $id, 'updated', $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}