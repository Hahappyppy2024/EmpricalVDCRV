<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SiteService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SiteController extends BaseController
{
    private const ROLES = ['customer'];

    private SiteService $service;

    public function __construct()
    {
        $this->service = new SiteService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'sites.php', [
            'pageTitle' => 'Sites',
            'activeNav' => 'sites',
            'section' => 'list',
            'sites' => $this->service->listFor($user),
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'sites.php', [
            'pageTitle' => 'Create site',
            'activeNav' => 'sites',
            'section' => 'form',
            'site' => null,
            'domains' => $this->service->domainsFor($user),
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

        [$error, $id] = $this->service->create(
            $user,
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['name'] ?? ''),
            (string) ($body['document_root'] ?? ''),
            (string) ($body['status'] ?? 'deployed')
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Site created.');
        if ($error === null) {
            return $this->redirect($response, '/sites/' . $id);
        }

        return $this->redirect($response, '/sites/new');
    }

    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $site = $this->service->getForUser((int) $args['id'], $user);
        if ($site === null) {
            return $this->error($response, 'Unknown or out-of-scope site.', 404);
        }

        return $this->render($request, $response, 'sites.php', [
            'pageTitle' => 'Site: ' . $site['name'],
            'activeNav' => 'sites',
            'section' => 'detail',
            'site' => $site,
            'domains' => $this->service->domainsFor($user),
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

        [$error] = $this->service->update(
            $user,
            (int) $args['id'],
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['name'] ?? ''),
            (string) ($body['document_root'] ?? ''),
            (string) ($body['status'] ?? 'deployed')
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Site updated.');

        return $this->redirect($response, '/sites/' . $args['id']);
    }

    public function deploy(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->deploy($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Site deployed.');

        return $this->redirect($response, '/sites/' . $args['id']);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);

        return $this->json($response, ['success' => true, 'records' => $this->service->listFor($user)]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->create(
            $user,
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['name'] ?? ''),
            (string) ($body['document_root'] ?? ''),
            (string) ($body['status'] ?? 'deployed')
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Site configuration created.',
            'record' => $this->service->getForUser($id, $user),
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

        [$error] = $this->service->update(
            $user,
            (int) $args['id'],
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['name'] ?? ''),
            (string) ($body['document_root'] ?? ''),
            (string) ($body['status'] ?? 'deployed')
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Site configuration updated.',
            'record' => $this->service->getForUser((int) $args['id'], $user),
        ]);
    }
}
