<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class TicketRepository
{
    public function listForUser(int $userId, string $role): array
    {
        if ($role === 'support' || $role === 'admin') {
            return Database::pdo()->query(
                'SELECT t.*, u.username AS customer_name, a.username AS assignee_name FROM tickets t
                 JOIN users u ON u.id = t.user_id LEFT JOIN users a ON a.id = t.assignee_id ORDER BY t.id DESC'
            )->fetchAll();
        }
        $stmt = Database::pdo()->prepare(
            'SELECT t.*, a.username AS assignee_name FROM tickets t LEFT JOIN users a ON a.id = t.assignee_id WHERE t.user_id = ? ORDER BY t.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId, string $role): ?array
    {
        $sql = 'SELECT t.*, u.username AS customer_name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?';
        $params = [$id];
        if (!in_array($role, ['support', 'admin'], true)) {
            $sql .= ' AND t.user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function create(int $userId, string $subject, string $body, string $priority): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO tickets (user_id, subject, body, priority, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $subject, $body, $priority, 'open']);
        return (int)Database::pdo()->lastInsertId();
    }

    public function replies(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.*, u.username, u.role FROM ticket_replies r JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? ORDER BY r.id'
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function reply(int $ticketId, int $userId, string $body): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$ticketId, $userId, $body]);
        $upd = Database::pdo()->prepare(
            'UPDATE tickets SET status = ?, updated_at = datetime("now"), assignee_id = COALESCE(assignee_id, ?) WHERE id = ?'
        );
        $upd->execute(['answered', $userId, $ticketId]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function setStatus(int $id, int $userId, string $role, string $status): void
    {
        $sql = 'UPDATE tickets SET status = ?, updated_at = datetime("now") WHERE id = ?';
        $params = [$status, $id];
        if (!in_array($role, ['support', 'admin'], true)) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function assign(int $id, int $assigneeId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE tickets SET assignee_id = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute([$assigneeId, $id]);
    }
}