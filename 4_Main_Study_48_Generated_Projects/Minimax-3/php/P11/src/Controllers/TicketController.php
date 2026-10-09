<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TicketRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TicketController
{
    public function __construct(private TicketRepository $tickets) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->tickets->listForUser((int)$user['id'], $user['role']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'tickets', ['user' => $user, 'items' => $items, 'flash' => Flash::pull()]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $t = $this->tickets->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$t) return View::json($response, ['error' => 'not_found'], 404);
        $replies = $this->tickets->replies((int)$t['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['ticket' => $t, 'replies' => $replies]);
        return View::render($response, 'tickets_show', ['user' => $user, 'ticket' => $t, 'replies' => $replies, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $subject = trim((string)($body['subject'] ?? ''));
        $bodyText = trim((string)($body['body'] ?? ''));
        $priority = trim((string)($body['priority'] ?? 'normal'));
        if (!Validator::nonEmpty($subject) || !Validator::nonEmpty($bodyText)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'validation'], 422);
            Flash::set('error', 'Subject and body are required'); return View::redirect($response, '/tickets?error=validation');
        }
        if (!Validator::oneOf($priority, ['low','normal','high','urgent'])) return View::json($response, ['error' => 'invalid_priority'], 422);
        $id = $this->tickets->create((int)$user['id'], $subject, $bodyText, $priority);
        AuditLogger::log((int)$user['id'], $user['role'], 'ticket.create', 'ticket', $id, $subject, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Ticket #$id opened");
        return View::redirect($response, '/tickets/' . $id);
    }

    public function reply(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $t = $this->tickets->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$t) return View::json($response, ['error' => 'not_found'], 404);
        $bodyText = trim((string)($request->getParsedBody()['body'] ?? ''));
        if (!Validator::nonEmpty($bodyText)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'validation'], 422);
            Flash::set('error', 'Reply body is required'); return View::redirect($response, '/tickets/' . $t['id']);
        }
        $rid = $this->tickets->reply((int)$t['id'], (int)$user['id'], $bodyText);
        AuditLogger::log((int)$user['id'], $user['role'], 'ticket.reply', 'ticket', (int)$t['id'], null, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $rid, 'status' => 'ok'], 201);
        return View::redirect($response, '/tickets/' . $t['id']);
    }

    public function setStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $t = $this->tickets->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$t) return View::json($response, ['error' => 'not_found'], 404);
        $status = trim((string)($request->getParsedBody()['status'] ?? ''));
        if (!Validator::oneOf($status, ['open','pending','answered','closed'])) return View::json($response, ['error' => 'invalid_status'], 422);
        $this->tickets->setStatus((int)$t['id'], (int)$user['id'], $user['role'], $status);
        AuditLogger::log((int)$user['id'], $user['role'], 'ticket.status', 'ticket', (int)$t['id'], $status, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function assign(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['support','admin'], true)) return View::json($response, ['error' => 'forbidden'], 403);
        $t = $this->tickets->findOwned((int)$args['id'], (int)$user['id'], $user['role']);
        if (!$t) return View::json($response, ['error' => 'not_found'], 404);
        $assignee = (int)($request->getParsedBody()['assignee_id'] ?? 0);
        if ($assignee <= 0) return View::json($response, ['error' => 'invalid_assignee'], 422);
        $this->tickets->assign((int)$t['id'], $assignee);
        AuditLogger::log((int)$user['id'], $user['role'], 'ticket.assign', 'ticket', (int)$t['id'], "to=$assignee", $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}