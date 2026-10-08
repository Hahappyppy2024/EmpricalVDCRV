<?php

declare(strict_types=1);

namespace Shop\Repository;

final class CartRepository extends Repository
{
    /**
     * @return list<array<string,mixed>>
     */
    public function items(int $userId): array
    {
        return $this->rows(
            'SELECT ci.id AS cart_id, ci.quantity, p.*, c.name AS category_name
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             JOIN categories c ON c.id = p.category_id
             WHERE ci.user_id = ?
             ORDER BY ci.id',
            [$userId]
        );
    }

    public function byProduct(int $userId, int $productId): ?array
    {
        return $this->row('SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
    }

    public function byItemId(int $userId, int $cartId): ?array
    {
        return $this->row('SELECT * FROM cart_items WHERE user_id = ? AND id = ?', [$userId, $cartId]);
    }

    public function add(int $userId, int $productId, int $quantity): void
    {
        $existing = $this->byProduct($userId, $productId);
        if ($existing !== null) {
            $this->exec('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?', [$quantity, $existing['id']]);
            return;
        }
        $this->exec('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)', [$userId, $productId, $quantity]);
    }

    public function updateQuantity(int $id, int $quantity): void
    {
        $this->exec('UPDATE cart_items SET quantity = ? WHERE id = ?', [$quantity, $id]);
    }

    public function remove(int $id): void
    {
        $this->exec('DELETE FROM cart_items WHERE id = ?', [$id]);
    }

    public function clear(int $userId): void
    {
        $this->exec('DELETE FROM cart_items WHERE user_id = ?', [$userId]);
    }

    public function count(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
