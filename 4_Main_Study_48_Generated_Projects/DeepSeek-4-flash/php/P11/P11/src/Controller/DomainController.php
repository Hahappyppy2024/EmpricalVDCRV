<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DomainService;
use App\Support\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DomainController extends BaseController
{
    private const ROLES = ['customer', 'admin'];

    private DomainService $service;

    public function __construct()
    {
        $this->service = new DomainService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'domains.php', [
            'pageTitle' => 'Domains',
            'activeNav' => 'domains',
            'section' => 'list',
            'domains' => $this->service->listForActor($user),
            'isAdmin' => $user['role'] === 'admin',
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'domains.php', [
            'pageTitle' => 'Add domain',
            'activeNav' => 'domains',
            'section' => 'form',
            'domain' => null,
            'isAdmin' => $user['role'] === 'admin',
        ]);
    }

    public function store(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $name = strtolower(trim((string) ($body['name'] ?? '')));
        $kind = (string) ($body['kind'] ?? 'domain');
        $status = (string) ($body['status'] ?? 'active');

        if ($name !== '' && !Validation::validDomainName($name)) {
            $this->flash($request, 'error', 'Enter a valid domain name.');
        } else {
            [$error, $id] = $this->service->create($user, $name, $kind, $status);
            if ($error === null) {
                $this->flash($request, 'success', 'Domain added.');
                return $this->redirect($response, '/domains/' . $id);
            }
            $this->flash($request, 'error', $error);
        }

        return $this->redirect($response, '/domains/new');
    }

    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $domain = $this->service->getForActor($user, (int) $args['id']);
        if ($domain === null) {
            return $this->error($response, 'Unknown or out-of-scope domain.', 404);
        }

        return $this->render($request, $response, 'domains.php', [
            'pageTitle' => 'Domain: ' . $domain['name'],
            'activeNav' => 'domains',
            'section' => 'detail',
            'domain' => $domain,
            'isAdmin' => $user['role'] === 'admin',
        ]);
    }

    public function update(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $name = strtolower(trim((string) ($body['name'] ?? '')));
        $kind = (string) ($body['kind'] ?? 'domain');
        $status = (string) ($body['status'] ?? 'active');

        [$error] = $this->service->update($user, (int) $args['id'], $name, $kind, $status);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Domain updated.');

        return $this->redirect($response, '/domains/' . $args['id']);
    }

    public function status(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $status = (string) ($body['status'] ?? 'active');
        $error = $this->service->updateStatusActor($user, (int) $args['id'], $status);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Domain status updated.');

        return $this->redirect($response, '/domains/' . $args['id']);
    }

    public function delete(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->deleteActor($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Domain deleted.');

        return $this->redirect($response, '/domains');
    }

    public function addDns(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        [$error, $dnsId] = $this->service->addDns(
            $user,
            (int) $args['id'],
            (string) ($body['type'] ?? 'A'),
            (string) ($body['name'] ?? ''),
            (string) ($body['value'] ?? ''),
            (int) ($body['ttl'] ?? 3600)
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'DNS record added.');

        return $this->redirect($response, '/domains/' . $args['id']);
    }

    public function deleteDns(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->deleteDns($user, (int) $args['id'], (int) $args['dnsId']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'DNS record deleted.');

        return $this->redirect($response, '/domains/' . $args['id']);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);

        return $this->json($response, ['success' => true, 'records' => $this->service->listForActor($user)]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $name = strtolower(trim((string) ($body['name'] ?? '')));
        $kind = (string) ($body['kind'] ?? 'domain');
        $status = (string) ($body['status'] ?? 'active');

        if ($name === '' || !Validation::validDomainName($name)) {
            return $this->error($response, 'A valid domain name is required.', 422);
        }

        [$error, $id] = $this->service->create($user, $name, $kind, $status);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Domain record created.',
            'record' => $this->service->getForActor($user, $id),
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $name = strtolower(trim((string) ($body['name'] ?? '')));
        $kind = (string) ($body['kind'] ?? 'domain');
        $status = (string) ($body['status'] ?? 'active');

        [$error] = $this->service->update($user, (int) $args['id'], $name, $kind, $status);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Domain record updated.',
            'record' => $this->service->getForActor($user, (int) $args['id']),
        ]);
    }
}
