<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SiteRepository;
use App\Repositories\DomainRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SiteController
{
    public function __construct(
        private SiteRepository $sites,
        private DomainRepository $domains,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->sites->listForUser((int)$user['id']);
        $isApi = str_starts_with($request->getUri()->getPath(), '/api/');
        if ($isApi) return View::json($response, ['items' => $items]);
        $myDomains = $this->domains->listForUser((int)$user['id']);
        return View::render($response, 'sites', [
            'user' => $user, 'items' => $items, 'myDomains' => $myDomains, 'flash' => Flash::pull(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $site = $this->sites->findOwned((int)$args['id'], (int)$user['id']);
        if (!$site) return View::json($response, ['error' => 'not_found'], 404);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['site' => $site]);
        return View::render($response, 'sites_show', ['user' => $user, 'site' => $site, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['site_name'] ?? ''));
        $docRoot = trim((string)($body['document_root'] ?? ''));
        $phpVersion = trim((string)($body['php_version'] ?? '8.3'));
        $domainId = isset($body['domain_id']) && $body['domain_id'] !== '' ? (int)$body['domain_id'] : null;

        if (!Validator::nonEmpty($name) || !Validator::nonEmpty($docRoot)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'validation'], 422);
            Flash::set('error', 'Site name and document root are required');
            return View::redirect($response, '/sites?error=validation');
        }
        if (!Validator::oneOf($phpVersion, ['7.4','8.0','8.1','8.2','8.3'])) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'php_version'], 422);
            Flash::set('error', 'Invalid PHP version');
            return View::redirect($response, '/sites?error=php');
        }
        if ($domainId !== null) {
            $dom = $this->domains->findOwned($domainId, (int)$user['id']);
            if (!$dom) return View::json($response, ['error' => 'domain_not_owned'], 403);
        }
        $id = $this->sites->create((int)$user['id'], $domainId, $name, $docRoot, $phpVersion);
        AuditLogger::log((int)$user['id'], $user['role'], 'site.create', 'site', $id, $name, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Site '$name' deployed");
        return View::redirect($response, '/sites/' . $id);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $site = $this->sites->findOwned((int)$args['id'], (int)$user['id']);
        if (!$site) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['site_name'] ?? $site['site_name']));
        $docRoot = trim((string)($body['document_root'] ?? $site['document_root']));
        $phpVersion = trim((string)($body['php_version'] ?? $site['php_version']));
        $status = trim((string)($body['status'] ?? $site['status']));
        $domainId = isset($body['domain_id']) && $body['domain_id'] !== '' ? (int)$body['domain_id'] : null;
        if (!Validator::nonEmpty($name) || !Validator::nonEmpty($docRoot) || !Validator::oneOf($status, ['deployed','pending','failed'])) {
            return View::json($response, ['error' => 'validation'], 422);
        }
        $this->sites->update((int)$site['id'], (int)$user['id'], $domainId, $name, $docRoot, $phpVersion, $status);
        AuditLogger::log((int)$user['id'], $user['role'], 'site.update', 'site', (int)$site['id'], $name, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $site = $this->sites->findOwned((int)$args['id'], (int)$user['id']);
        if (!$site) return View::json($response, ['error' => 'not_found'], 404);
        $this->sites->delete((int)$site['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'site.delete', 'site', (int)$site['id'], $site['site_name'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'Site removed');
        return View::redirect($response, '/sites');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}