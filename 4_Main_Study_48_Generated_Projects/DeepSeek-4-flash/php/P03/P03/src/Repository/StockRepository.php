<?php

declare(strict_types=1);

namespace Shop\Repository;

final class StockRepository extends Repository
{
    /**
     * @return list<array<string,mixed>>
     */
    public function movements(int $productId): array
    {
        return $this->rows(
            'SELECT m.*, u.name AS user_name
             FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id
             WHERE m.product_id = ? ORDER BY m.id DESC LIMIT 100',
            [$productId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 100): array
    {
        return $this->rows(
            'SELECT m.*, u.name AS user_name, p.name AS product_name
             FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id LEFT JOIN products p ON p.id = m.product_id
             ORDER BY m.id DESC LIMIT ?',
            [$limit]
        );
    }

    public function add(int $productId, int $delta, string $reason, ?int $userId): int
    {
        $this->exec(
            'INSERT INTO stock_movements (product_id, delta, reason, user_id) VALUES (?, ?, ?, ?)',
            [$productId, $delta, $reason, $userId]
        );
        return $this->insertId();
    }

    public function lowStock(int $threshold): array
    {
        return $this->rows(
            'SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.stock <= ? ORDER BY p.stock',
            [$threshold]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function stockValue(): array
    {
        return $this->rows(
            'SELECT p.id, p.name, p.stock, p.price, ROUND(p.stock * p.price, 2) AS value FROM products p ORDER BY value DESC'
        );
    }
}
