<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class ReportRepository
{
    public static function sales(?int $sellerId = null): array
    {
        $sql = 'SELECT o.id, o.reference, o.status, o.total_cents, o.placed_at, u.display_name AS customer_name
                FROM orders o JOIN users u ON o.user_id = u.id';
        $params = [];
        if ($sellerId !== null) {
            $sql .= ' JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = :s';
            $sql .= ' GROUP BY o.id ORDER BY o.id DESC';
            $params[':s'] = $sellerId;
        } else {
            $sql .= ' ORDER BY o.id DESC';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function inventoryReport(): array
    {
        return Database::pdo()
            ->query(
                'SELECT p.id, p.sku, p.name,
                        COALESCE(i.quantity, 0) AS quantity,
                        i.restock_threshold,
                        (CASE WHEN COALESCE(i.quantity, 0) <= i.restock_threshold THEN 1 ELSE 0 END) AS needs_restock
                 FROM products p LEFT JOIN inventory i ON p.id = i.product_id
                 ORDER BY p.id ASC'
            )
            ->fetchAll();
    }

    public static function customers(): array
    {
        return Database::pdo()
            ->query(
                'SELECT u.id, u.display_name, u.email, u.created_at,
                        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
                        (SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE user_id = u.id) AS lifetime_value_cents
                 FROM users u
                 WHERE u.role IN ("customer","seller","admin")
                 ORDER BY u.id ASC'
            )
            ->fetchAll();
    }

    public static function toCsv(array $rows, array $headers): string
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = (string)($row[$h] ?? '');
            }
            fputcsv($fp, $line);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv === false ? '' : $csv;
    }
}