<?php

declare(strict_types=1);

namespace Shop\Repository;

final class OrderRepository extends Repository
{
    /**
     * @return list<array<string,mixed>>
     */
    public function forUser(int $userId, array $filters = []): array
    {
        $sql = 'SELECT * FROM orders WHERE user_id = ?';
        $params = [$userId];
        $sql = $this->applyFilters($sql, $params, $filters);
        return $this->rows($sql, $params);
    }

    /**
     * Orders that contain at least one product sold by the given seller.
     *
     * @return list<array<string,mixed>>
     */
    public function forSeller(int $sellerId, array $filters = []): array
    {
        $sql = 'SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM orders o
                JOIN users u ON u.id = o.user_id
                WHERE o.id IN (
                    SELECT oi.order_id FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE p.seller_id = ?
                )';
        $params = [$sellerId];
        $sql = $this->applyFilters($sql, $params, $filters);
        return $this->rows($sql, $params);
    }

    /**
     * Whether an order contains at least one product sold by the given seller.
     */
    public function hasSellerProduct(int $orderId, int $sellerId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? AND p.seller_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId, $sellerId]);
        return $stmt->fetch() !== false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id';
        $params = [];
        $sql = $this->applyFilters($sql, $params, $filters);
        return $this->rows($sql, $params);
    }

    /**
     * @param list<string|int|float> $params
     */
    private function applyFilters(string $sql, array &$params, array $filters): string
    {
        $where = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['number'])) {
            $where[] = 'number = ?';
            $params[] = $filters['number'];
        }
        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $where[] = 'user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if ($where !== []) {
            $prefix = (stripos($sql, ' WHERE ') !== false) ? ' AND ' : ' WHERE ';
            $sql .= $prefix . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';
        return $sql;
    }

    public function byId(int $id): ?array
    {
        return $this->row(
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?',
            [$id]
        );
    }

    public function byNumber(string $number): ?array
    {
        return $this->row(
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.user_id WHERE o.number = ?',
            [$number]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function items(int $orderId): array
    {
        return $this->rows('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function history(int $orderId): array
    {
        return $this->rows(
            'SELECT h.*, u.name AS changed_by_name
             FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.order_id = ? ORDER BY h.id',
            [$orderId]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $this->exec(
            'INSERT INTO orders (number, user_id, address_id, status, subtotal, shipping, tax, discount, total, payment_method, payment_reference, promotion_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['number'],
                $data['user_id'],
                $data['address_id'],
                $data['status'],
                $data['subtotal'],
                $data['shipping'],
                $data['tax'],
                $data['discount'],
                $data['total'],
                $data['payment_method'],
                $data['payment_reference'],
                $data['promotion_id'] ?? null,
            ]
        );
        return $this->insertId();
    }

    public function addItem(int $orderId, int $productId, string $name, float $unitPrice, int $quantity, float $lineTotal): void
    {
        $this->exec(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)',
            [$orderId, $productId, $name, $unitPrice, $quantity, $lineTotal]
        );
    }

    public function addHistory(int $orderId, ?string $from, string $to, ?int $changedBy, string $comment): void
    {
        $this->exec(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $from, $to, $changedBy, $comment]
        );
    }

    public function setStatus(int $orderId, string $status, string $paymentReference = ''): void
    {
        if ($paymentReference !== '') {
            $this->exec('UPDATE orders SET status = ?, payment_reference = ?, updated_at = datetime(\'now\') WHERE id = ?', [$status, $paymentReference, $orderId]);
            return;
        }
        $this->exec('UPDATE orders SET status = ?, updated_at = datetime(\'now\') WHERE id = ?', [$status, $orderId]);
    }

    public function delete(int $orderId): void
    {
        $this->exec('DELETE FROM order_items WHERE order_id = ?', [$orderId]);
        $this->exec('DELETE FROM order_status_history WHERE order_id = ?', [$orderId]);
        $this->exec('DELETE FROM orders WHERE id = ?', [$orderId]);
    }

    public function setAddress(int $orderId, int $addressId): void
    {
        $this->exec('UPDATE orders SET address_id = ?, updated_at = datetime(\'now\') WHERE id = ?', [$addressId, $orderId]);
    }

    public function nextNumber(): string
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM orders');
        $next = (int) $stmt->fetchColumn();
        return 'ORD-' . date('Y') . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function totals(): array
    {
        $row = $this->row(
            'SELECT COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN status = \'paid\' THEN total END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status = \'shipped\' THEN total END), 0) AS shipped,
                    COALESCE(SUM(CASE WHEN status = \'delivered\' THEN total END), 0) AS delivered,
                    COALESCE(SUM(CASE WHEN status = \'refunded\' THEN total END), 0) AS refunded
             FROM orders'
        );
        $row['orders'] = (int) $row['orders'];
        foreach (['revenue', 'paid', 'shipped', 'delivered', 'refunded'] as $k) {
            $row[$k] = round((float) $row[$k], 2);
        }
        return $row;
    }

    public function statusCounts(): array
    {
        $rows = $this->rows('SELECT status, COUNT(*) AS count FROM orders GROUP BY status');
        $map = ['pending' => 0, 'paid' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0, 'refunded' => 0];
        foreach ($rows as $row) {
            $map[$row['status']] = (int) $row['count'];
        }
        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function topProducts(int $limit = 5): array
    {
        return $this->rows(
            'SELECT product_id, product_name, SUM(quantity) AS sold, SUM(line_total) AS revenue
             FROM order_items
             WHERE product_id IS NOT NULL
             GROUP BY product_id, product_name
             ORDER BY revenue DESC
             LIMIT ?',
            [$limit]
        );
    }

    public function customerSpend(int $userId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = ? AND status IN (\'paid\', \'shipped\', \'delivered\')'
        );
        $stmt->execute([$userId]);
        return round((float) $stmt->fetchColumn(), 2);
    }
}
