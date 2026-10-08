<?php

declare(strict_types=1);

namespace Shop\Repository;

final class ReviewRepository extends Repository
{
    public function byId(int $id): ?array
    {
        return $this->row(
            'SELECT r.*, u.name AS customer_name, p.name AS product_name
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN products p ON p.id = r.product_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function byProduct(int $productId, bool $onlyApproved = true): array
    {
        $sql = 'SELECT r.*, u.name AS customer_name
                FROM reviews r JOIN users u ON u.id = r.user_id
                WHERE r.product_id = ?';
        if ($onlyApproved) {
            $sql .= ' AND r.status = \'approved\'';
        }
        $sql .= ' ORDER BY r.id DESC';
        return $this->rows($sql, [$productId]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pending(): array
    {
        return $this->rows(
            'SELECT r.*, u.name AS customer_name, p.name AS product_name
             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
             WHERE r.status = \'pending\' ORDER BY r.id'
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 50): array
    {
        return $this->rows(
            'SELECT r.*, u.name AS customer_name, p.name AS product_name
             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
             ORDER BY r.id DESC LIMIT ?',
            [$limit]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(int $productId): array
    {
        $row = $this->row(
            'SELECT COUNT(*) AS count, COALESCE(AVG(rating), 0) AS average
             FROM reviews WHERE product_id = ? AND status = \'approved\'',
            [$productId]
        );
        return [
            'count' => (int) ($row['count'] ?? 0),
            'average' => round((float) ($row['average'] ?? 0), 2),
        ];
    }

    public function create(int $productId, int $userId, int $rating, string $title, string $text): int
    {
        $this->exec(
            'INSERT INTO reviews (product_id, user_id, rating, title, text, status) VALUES (?, ?, ?, ?, ?, \'pending\')',
            [$productId, $userId, $rating, $title, $text]
        );
        return $this->insertId();
    }

    public function moderate(int $id, string $status, int $moderatorId): void
    {
        $this->exec(
            'UPDATE reviews SET status = ?, moderated_by = ?, moderated_at = datetime(\'now\') WHERE id = ?',
            [$status, $moderatorId, $id]
        );
    }
}
