<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class InventoryRepository
{
    public static function forProduct(int $productId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM inventory WHERE product_id = :p');
        $stmt->execute([':p' => $productId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function adjust(int $productId, int $delta, string $reason, ?int $actorId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = self::forProduct($productId);
            $newQty = max(0, ($row['quantity'] ?? 0) + $delta);
            if ($row === null) {
                $pdo->prepare('INSERT INTO inventory (product_id, quantity, last_restock_at) VALUES (:p, :q, :now)')
                    ->execute([':p' => $productId, ':q' => $newQty, ':now' => date('Y-m-d H:i:s')]);
            } else {
                $pdo->prepare('UPDATE inventory SET quantity = :q, last_restock_at = :now, updated_at = datetime("now") WHERE product_id = :p')
                    ->execute([':q' => $newQty, ':now' => date('Y-m-d H:i:s'), ':p' => $productId]);
            }
            $pdo->prepare('INSERT INTO inventory_events (product_id, delta, reason, actor_id) VALUES (:p, :d, :r, :a)')
                ->execute([':p' => $productId, ':d' => $delta, ':r' => $reason, ':a' => $actorId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setRestockThreshold(int $productId, int $threshold): void
    {
        $pdo = Database::pdo();
        if (self::forProduct($productId) === null) {
            $pdo->prepare('INSERT INTO inventory (product_id, quantity, restock_threshold) VALUES (:p, 0, :t)')
                ->execute([':p' => $productId, ':t' => $threshold]);
        } else {
            $pdo->prepare('UPDATE inventory SET restock_threshold = :t, updated_at = datetime("now") WHERE product_id = :p')
                ->execute([':t' => $threshold, ':p' => $productId]);
        }
    }

    public static function eventsFor(int $productId, int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM inventory_events WHERE product_id = :p ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([':p' => $productId]);
        return $stmt->fetchAll();
    }
}