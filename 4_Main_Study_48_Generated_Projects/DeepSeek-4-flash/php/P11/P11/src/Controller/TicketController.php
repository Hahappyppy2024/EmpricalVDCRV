<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SupportTicketService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TicketController extends BaseController
{
    private const ROLES = ['customer', 'support'];

    private SupportTicketService $service;

    public function __construct()
    {
        $this->service = new SupportTicketService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'tickets.php', [
            'pageTitle' => 'Support tickets',
            'activeNav' => 'tickets',
            'section' => 'list',
            'tickets' => $user['role'] === 'support' ? $this->service->listAll() : $this->service->listFor($user),
            'isSupport' => $user['role'] === 'support',
        ]);
    }

    public function create(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }

        return $this->render($request, $response, 'tickets.php', [
            'pageTitle' => 'Open ticket',
            'activeNav' => 'tickets',
            'section' => 'form',
            'ticket' => null,
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

        [$error, $id] = $this->service->create($user, (string) ($body['subject'] ?? ''), (string) ($body['body'] ?? ''));
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Ticket opened.');
        if ($error === null) {
            return $this->redirect($response, '/tickets/' . $id);
        }

        return $this->redirect($response, '/tickets/new');
    }

    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $ticket = $this->service->get($user, (int) $args['id']);
        if ($ticket === null) {
            return $this->error($response, 'Unknown or out-of-scope ticket.', 404);
        }

        return $this->render($request, $response, 'tickets.php', [
            'pageTitle' => 'Ticket: ' . $ticket['subject'],
            'activeNav' => 'tickets',
            'section' => 'detail',
            'ticket' => $ticket,
            'isSupport' => in_array($user['role'], ['support', 'admin'], true),
        ]);
    }

    public function reply(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        $error = $this->service->reply($user, (int) $args['id'], (string) ($body['body'] ?? ''));
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Reply posted.');

        return $this->redirect($response, '/tickets/' . $args['id']);
    }

    public function status(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        $error = $this->service->setStatus($user, (int) $args['id'], (string) ($body['status'] ?? 'open'));
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Ticket status updated.');

        return $this->redirect($response, '/tickets/' . $args['id']);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);

        return $this->json($response, [
            'success' => true,
            'records' => $user['role'] === 'support' ? $this->service->listAll() : $this->service->listFor($user),
        ]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->create($user, (string) ($body['subject'] ?? ''), (string) ($body['body'] ?? ''));
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Ticket opened.',
            'record' => $this->service->get($user, $id),
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

        if (isset($body['status'])) {
            $error = $this->service->setStatus($user, (int) $args['id'], (string) $body['status']);
        } else {
            $error = $this->service->reply($user, (int) $args['id'], (string) ($body['body'] ?? ''));
        }
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Ticket updated.',
            'record' => $this->service->get($user, (int) $args['id']),
        ]);
    }
}
