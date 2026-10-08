<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CertificateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CertificateController extends BaseController
{
    private const ROLES = ['customer'];

    private CertificateService $service;

    public function __construct()
    {
        $this->service = new CertificateService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'certificates.php', [
            'pageTitle' => 'SSL certificates',
            'activeNav' => 'certificates',
            'section' => 'list',
            'certificates' => $this->service->listFor($user),
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'certificates.php', [
            'pageTitle' => 'Request certificate',
            'activeNav' => 'certificates',
            'section' => 'form',
            'certificate' => null,
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

        [$error, $id] = $this->service->request(
            $user,
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['provider'] ?? 'letsencrypt')
        );
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Certificate request submitted.');
        if ($error === null) {
            return $this->redirect($response, '/certificates/' . $id);
        }

        return $this->redirect($response, '/certificates/new');
    }

    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $cert = $this->service->getForUser((int) $args['id'], $user);
        if ($cert === null) {
            return $this->error($response, 'Unknown or out-of-scope certificate.', 404);
        }

        return $this->render($request, $response, 'certificates.php', [
            'pageTitle' => 'Certificate: ' . $cert['domain_name'],
            'activeNav' => 'certificates',
            'section' => 'detail',
            'certificate' => $cert,
        ]);
    }

    public function renew(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->renew($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Certificate renewed.');

        return $this->redirect($response, '/certificates/' . $args['id']);
    }

    public function upload(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $domainId = isset($body['domain_id']) && $body['domain_id'] !== '' ? (int) $body['domain_id'] : null;
        $certText = (string) ($body['certificate_text'] ?? '');
        $keyText = (string) ($body['private_key_text'] ?? '');

        [$error, $id] = $this->service->upload($user, (int) $domainId, $certText, $keyText);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Certificate uploaded.');
        if ($error === null) {
            return $this->redirect($response, '/certificates/' . $id);
        }

        return $this->redirect($response, '/certificates/new');
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

        [$error, $id] = $this->service->request(
            $user,
            (int) ($body['domain_id'] ?? 0),
            (string) ($body['provider'] ?? 'letsencrypt')
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Certificate request submitted.',
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
        $error = $this->service->renew($user, (int) $args['id']);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'message' => 'Certificate renewed.']);
    }
}
