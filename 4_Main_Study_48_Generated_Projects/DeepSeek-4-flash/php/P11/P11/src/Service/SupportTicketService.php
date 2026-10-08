<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\TicketRepository;

final class SupportTicketService
{
    private TicketRepository $tickets;

    private AuditRepository $audit;

    public function __construct()
    {
        $this->tickets = new TicketRepository();
        $this->audit = new AuditRepository();
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, ticketId]
     */
    public function create(array $user, string $subject, string $body): array
    {
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '') {
            return ['Ticket subject is required.', null];
        }
        if ($body === '') {
            return ['Ticket message is required.', null];
        }

        $id = $this->tickets->create((int) $user['id'], $subject, $body);
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'support_tickets', 'ticket', (string) $id, 'Opened ticket "' . $subject . '"');

        return [null, $id];
    }

    public function listFor(array $user): array
    {
        return $this->tickets->allForUser((int) $user['id']);
    }

    public function listAll(): array
    {
        return $this->tickets->all();
    }

    /**
     * Customers may only read their own tickets; support staff may read all.
     */
    public function get(array $user, int $id): ?array
    {
        if ($user['role'] === 'support' || $user['role'] === 'admin') {
            $ticket = $this->tickets->find($id);
        } else {
            $ticket = $this->tickets->findForUser($id, (int) $user['id']);
        }
        if ($ticket === null) {
            return null;
        }
        $ticket['messages'] = $this->tickets->messages($id);

        return $ticket;
    }

    public function reply(array $user, int $ticketId, string $body): ?string
    {
        $ticket = $this->get($user, $ticketId);
        if ($ticket === null) {
            return 'Unknown or out-of-scope ticket.';
        }
        $body = trim($body);
        if ($body === '') {
            return 'Reply message is required.';
        }

        $this->tickets->addMessage($ticketId, (int) $user['id'], (string) $user['role'], $body);
        $this->tickets->updateStatus($ticketId, 'answered');
        $this->audit->record((int) $user['id'], $user['username'], 'reply', 'support_tickets', 'ticket', (string) $ticketId, 'Replied to ticket');

        return null;
    }

    public function setStatus(array $user, int $ticketId, string $status): ?string
    {
        $ticket = $this->get($user, $ticketId);
        if ($ticket === null) {
            return 'Unknown or out-of-scope ticket.';
        }
        if (!in_array($status, ['open', 'answered', 'closed'], true)) {
            return 'Invalid ticket status.';
        }
        $this->tickets->updateStatus($ticketId, $status);
        $this->audit->record((int) $user['id'], $user['username'], 'status', 'support_tickets', 'ticket', (string) $ticketId, 'Set ticket status to ' . $status);

        return null;
    }
}
