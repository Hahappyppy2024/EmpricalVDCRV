<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class TicketRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS message_count FROM tickets t WHERE t.user_id = ? ORDER BY t.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT t.*, u.username, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS message_count FROM tickets t JOIN users u ON u.id = t.user_id ORDER BY t.id DESC'
        )->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tickets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $ticket = $stmt->fetch();

        return $ticket ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.username FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        return $ticket ?: null;
    }

    public function create(int $userId, string $subject, string $body): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO tickets (user_id, subject, status, created_at) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $subject, 'open', $now]);
        $ticketId = (int) $this->db->lastInsertId();

        $this->addMessage($ticketId, $userId, 'customer', $body);

        return $ticketId;
    }

    public function messages(int $ticketId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.username FROM ticket_messages m JOIN users u ON u.id = m.author_id WHERE m.ticket_id = ? ORDER BY m.id ASC'
        );
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    public function addMessage(int $ticketId, int $authorId, string $authorRole, string $body): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ticket_messages (ticket_id, author_id, author_role, body, created_at) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$ticketId, $authorId, $authorRole, $body, date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
}
