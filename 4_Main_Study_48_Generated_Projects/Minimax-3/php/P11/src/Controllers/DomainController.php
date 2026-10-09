<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Flash;
use App\Services\AuditLogger;
use App\Services\Validator;
use App\View;
use App\Repositories\DomainRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DomainController
{
    public function __construct(private DomainRepository $domains) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $isApi = str_starts_with($request->getUri()->getPath(), '/api/');
        if ($isApi) {
            $items = $user['role'] === 'admin' ? $this->domains->listAll() : $this->domains->listForUser((int)$user['id']);
            return View::json($response, ['items' => $items]);
        }
        $items = $user['role'] === 'admin' ? $this->domains->listAll() : $this->domains->listForUser((int)$user['id']);
        return View::render($response, 'domains', [
            'user'  => $user,
            'items' => $items,
            'flash' => Flash::pull(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domain = $this->domains->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$domain) return View::json($response, ['error' => 'not_found'], 404);
        $records = $this->domains->recordsFor((int)$domain['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return View::json($response, ['domain' => $domain, 'records' => $records]);
        }
        return View::render($response, 'domains_show', [
            'user' => $user, 'domain' => $domain, 'records' => $records, 'flash' => Flash::pull(),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $domain = trim((string)($body['domain'] ?? ''));
        $type   = trim((string)($body['type'] ?? 'domain'));
        $docRoot = trim((string)($body['document_root'] ?? ''));

        if (!Validator::domain($domain)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_domain'], 422);
            Flash::set('error', 'Invalid domain'); return View::redirect($response, '/domains?error=invalid');
        }
        if (!Validator::oneOf($type, ['domain','subdomain','alias'])) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_type'], 422);
            Flash::set('error', 'Invalid type'); return View::redirect($response, '/domains?error=type');
        }
        if ($docRoot === '') $docRoot = '/home/' . preg_replace('/[^a-z0-9_-]/i', '', $user['username']) . '/public_html';
        if ($this->domains->existsForUser((int)$user['id'], $domain)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'duplicate'], 409);
            Flash::set('error', 'Domain already exists'); return View::redirect($response, '/domains?error=duplicate');
        }
        $id = $this->domains->create((int)$user['id'], $domain, $type, $docRoot);
        AuditLogger::log((int)$user['id'], $user['role'], 'domain.create', 'domain', $id, $domain, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Domain $domain added");
        return View::redirect($response, '/domains/' . $id);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domain = $this->domains->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$domain) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $status = trim((string)($body['status'] ?? $domain['status']));
        if (!Validator::oneOf($status, ['active','disabled','pending'])) return View::json($response, ['error' => 'invalid_status'], 422);
        $this->domains->updateStatus((int)$domain['id'], (int)$user['id'], $status);
        AuditLogger::log((int)$user['id'], $user['role'], 'domain.update', 'domain', (int)$domain['id'], "status=$status", $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domain = $this->domains->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$domain) return View::json($response, ['error' => 'not_found'], 404);
        $this->domains->delete((int)$domain['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'domain.delete', 'domain', (int)$domain['id'], $domain['domain'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'Domain removed'); return View::redirect($response, '/domains');
    }

    public function addRecord(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domain = $this->domains->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$domain) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['name'] ?? '@'));
        $type = trim((string)($body['type'] ?? 'A'));
        $value = trim((string)($body['value'] ?? ''));
        $ttl = (int)($body['ttl'] ?? 3600);
        if (!Validator::oneOf($type, ['A','AAAA','CNAME','MX','TXT']) || $value === '') {
            return View::json($response, ['error' => 'invalid_record'], 422);
        }
        $rid = $this->domains->addRecord((int)$domain['id'], $name, $type, $value, $ttl);
        AuditLogger::log((int)$user['id'], $user['role'], 'dns.create', 'dns_record', $rid, "$name $type $value", $this->ip($request));
        return View::json($response, ['id' => $rid, 'status' => 'ok'], 201);
    }

    public function deleteRecord(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $domain = $this->domains->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$domain) return View::json($response, ['error' => 'not_found'], 404);
        $this->domains->deleteRecord((int)$args['rid'], (int)$domain['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'dns.delete', 'dns_record', (int)$args['rid'], null, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}