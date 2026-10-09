<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SslRepository;
use App\Repositories\DomainRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SslController
{
    public function __construct(
        private SslRepository $ssl,
        private DomainRepository $domains,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->ssl->listForUser((int)$user['id']);
        $myDomains = $this->domains->listForUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'ssl', ['user' => $user, 'items' => $items, 'myDomains' => $myDomains, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $domainId = (int)($body['domain_id'] ?? 0);
        $cn = trim((string)($body['common_name'] ?? ''));
        $issuer = trim((string)($body['issuer'] ?? 'AetherPanel CA (test)'));
        $cert = trim((string)($body['cert_pem'] ?? ''));
        $key = trim((string)($body['key_pem'] ?? ''));
        $validFrom = trim((string)($body['valid_from'] ?? date('Y-m-d')));
        $validTo = trim((string)($body['valid_to'] ?? date('Y-m-d', strtotime('+90 days'))));
        $status = trim((string)($body['status'] ?? 'active'));

        $dom = $this->domains->findOwned($domainId, (int)$user['id']);
        if (!$dom) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'domain_not_owned'], 403);
            Flash::set('error', 'Unknown domain'); return View::redirect($response, '/ssl?error=domain');
        }
        if (!Validator::nonEmpty($cn) || !str_contains($cert, 'BEGIN CERTIFICATE') || !str_contains($key, 'PRIVATE KEY')) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_cert'], 422);
            Flash::set('error', 'Certificate and key are required (PEM)'); return View::redirect($response, '/ssl?error=cert');
        }
        if (!Validator::oneOf($status, ['active','expired','revoked'])) return View::json($response, ['error' => 'invalid_status'], 422);
        if ($this->ssl->existsForDomain($domainId, $cn)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'duplicate'], 409);
            Flash::set('error', 'Certificate already exists for that domain'); return View::redirect($response, '/ssl?error=duplicate');
        }
        $id = $this->ssl->create((int)$user['id'], $domainId, $cn, $issuer, $cert, $key, $validFrom, $validTo, $status);
        AuditLogger::log((int)$user['id'], $user['role'], 'certificate.create', 'certificate', $id, $cn, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Certificate for $cn installed");
        return View::redirect($response, '/ssl');
    }

    public function renew(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $cert = $this->ssl->findOwned((int)$args['id'], (int)$user['id']);
        if (!$cert) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $validTo = trim((string)($body['valid_to'] ?? date('Y-m-d', strtotime('+90 days'))));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validTo)) return View::json($response, ['error' => 'invalid_date'], 422);
        $this->ssl->renew((int)$cert['id'], (int)$user['id'], $validTo);
        AuditLogger::log((int)$user['id'], $user['role'], 'certificate.renew', 'certificate', (int)$cert['id'], $validTo, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $cert = $this->ssl->findOwned((int)$args['id'], (int)$user['id']);
        if (!$cert) return View::json($response, ['error' => 'not_found'], 404);
        $this->ssl->revoke((int)$cert['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'certificate.revoke', 'certificate', (int)$cert['id'], $cert['common_name'], $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}