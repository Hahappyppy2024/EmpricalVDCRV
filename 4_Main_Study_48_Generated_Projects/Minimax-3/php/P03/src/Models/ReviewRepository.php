<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class ReviewRepository
{
    public static function create(int $productId, int $userId, int $rating, string $title, string $body): array
    {
        $pdo = Database::pdo();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO reviews (product_id, user_id, rating, title, body, status)
                 VALUES (:p, :u, :r, :t, :b, "pending")'
            );
            $stmt->execute([
                ':p' => $productId,
                ':u' => $userId,
                ':r' => $rating,
                ':t' => $title,
                ':b' => $body,
            ]);
            $id = (int)$pdo->lastInsertId();
            return self::find($id) ?? [];
        } catch (\PDOException $e) {
            $stmt = $pdo->prepare('SELECT * FROM reviews WHERE product_id = :p AND user_id = :u');
            $stmt->execute([':p' => $productId, ':u' => $userId]);
            return ['duplicate' => true] + ($stmt->fetch() ?: []);
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reviews WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forProduct(int $productId, bool $onlyApproved = true): array
    {
        $sql = 'SELECT r.*, u.display_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = :p';
        if ($onlyApproved) {
            $sql .= ' AND r.status = "approved"';
        }
        $sql .= ' ORDER BY r.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':p' => $productId]);
        return $stmt->fetchAll();
    }

    public static function pending(): array
    {
        return Database::pdo()
            ->query(
                'SELECT r.*, p.name AS product_name, u.display_name AS author
                 FROM reviews r JOIN products p ON r.product_id = p.id JOIN users u ON r.user_id = u.id
                 WHERE r.status = "pending"
                 ORDER BY r.id ASC'
            )
            ->fetchAll();
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return false;
        }
        Database::pdo()->prepare(
            'UPDATE reviews SET status = :s, updated_at = datetime("now") WHERE id = :id'
        )->execute([':s' => $status, ':id' => $id]);
        return true;
    }
}