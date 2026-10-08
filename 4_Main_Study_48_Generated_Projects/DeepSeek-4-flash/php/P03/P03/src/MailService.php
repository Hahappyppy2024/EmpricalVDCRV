<?php

declare(strict_types=1);

namespace Shop;

use PDO;

/**
 * Deterministic local mail adapter: messages are stored in the mail_logs
 * table so the application works fully offline and reset links are visible.
 */
final class MailService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function send(string $recipient, string $subject, string $body): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO mail_logs (recipient, subject, body) VALUES (?, ?, ?)');
        $stmt->execute([$recipient, $subject, $body]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sentTo(string $recipient): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mail_logs WHERE recipient = ? ORDER BY id DESC LIMIT 20');
        $stmt->execute([$recipient]);
        return $stmt->fetchAll();
    }
}
