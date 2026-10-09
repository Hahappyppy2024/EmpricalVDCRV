<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;
use Shop\Support\Validator;
use Shop\Support\Payment;

final class OrderRepository
{
    public static function create(int $userId, int $cartId, array $totals, array $data): ?array
    {
        $pdo = Database::pdo();
        $items = $totals['items'];
        if (!$items) {
            return null;
        }
        $shippingAddressId = (int)($data['shipping_address_id'] ?? 0) ?: null;
        if (!self::ownsAddress($userId, $shippingAddressId)) {
            return null;
        }
        $payment = Payment::charge(
            (string)($data['payment_method'] ?? 'card'),
            (string)($data['payment_token'] ?? 'tok_ok'),
            (int)$totals['total_cents']
        );
        if (!$payment['ok']) {
            return ['payment_declined' => true, 'message' => $payment['message']];
        }
        return Database::transactional(function ($pdo) use ($userId, $cartId, $totals, $items, $shippingAddressId, $payment) {
            $reference = 'ORD-' . str_pad((string)time(), 8, '0', STR_PAD_LEFT) . '-' . bin2hex(random_bytes(2));
            $stmt = $pdo->prepare(
                'INSERT INTO orders (reference, user_id, status, subtotal_cents, discount_cents, total_cents, currency, promotion_id, shipping_address_id, payment_fixture_id)
                 VALUES (:ref, :uid, "paid", :sub, :disc, :tot, :cur, :prom, :addr, :pay)'
            );
            $stmt->execute([
                ':ref' => $reference,
                ':uid' => $userId,
                ':sub' => (int)$totals['subtotal_cents'],
                ':disc' => (int)$totals['discount_cents'],
                ':tot' => (int)$totals['total_cents'],
                ':cur' => 'USD',
                ':prom' => $totals['promotion']['id'] ?? null,
                ':addr' => $shippingAddressId,
                ':pay' => (string)$payment['reference'],
            ]);
            $orderId = (int)$pdo->lastInsertId();
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, seller_id, product_name, unit_price_cents, quantity, line_total_cents)
                 VALUES (:o, :p, :s, :n, :u, :q, :l)'
            );
            $eventStmt = $pdo->prepare(
                'INSERT INTO order_events (order_id, actor_id, from_status, to_status, note)
                 VALUES (:o, :a, NULL, "paid", :note)'
            );
            $invStmt = $pdo->prepare(
                'SELECT quantity FROM inventory WHERE product_id = :p'
            );
            $invUpd = $pdo->prepare(
                'UPDATE inventory SET quantity = quantity - :q, updated_at = datetime("now") WHERE product_id = :p AND quantity >= :q'
            );
            $invEvent = $pdo->prepare(
                'INSERT INTO inventory_events (product_id, delta, reason, actor_id) VALUES (:p, :d, :r, :a)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    ':o' => $orderId,
                    ':p' => (int)$it['product_id'],
                    ':s' => (int)ProductRepository::find((int)$it['product_id'])['seller_id'],
                    ':n' => (string)$it['name'],
                    ':u' => (int)$it['unit_price_cents'],
                    ':q' => (int)$it['quantity'],
                    ':l' => (int)$it['line_total'],
                ]);
                $invStmt->execute([':p' => (int)$it['product_id']]);
                $qty = (int)$it['quantity'];
                $invUpd->execute([':q' => $qty, ':p' => (int)$it['product_id']]);
                $invEvent->execute([
                    ':p' => (int)$it['product_id'],
                    ':d' => -$qty,
                    ':r' => 'order ' . $reference,
                    ':a' => $userId,
                ]);
            }
            $eventStmt->execute([
                ':o' => $orderId,
                ':a' => $userId,
                ':note' => 'Order placed via checkout.',
            ]);
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :c')->execute([':c' => $cartId]);
            $pdo->prepare('UPDATE carts SET promotion_id = NULL WHERE id = :c')->execute([':c' => $cartId]);
            return ['order_id' => $orderId, 'reference' => $reference, 'payment' => $payment];
        });
    }

    public static function ownsAddress(int $userId, ?int $addressId): bool
    {
        if ($addressId === null) {
            return true;
        }
        $stmt = Database::pdo()->prepare('SELECT user_id FROM addresses WHERE id = :a');
        $stmt->execute([':a' => $addressId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return (int)$row['user_id'] === $userId;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByReference(string $ref): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE reference = :r');
        $stmt->execute([':r' => $ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function items(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM order_items WHERE order_id = :o ORDER BY id ASC'
        );
        $stmt->execute([':o' => $orderId]);
        return $stmt->fetchAll();
    }

    public static function events(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM order_events WHERE order_id = :o ORDER BY id ASC'
        );
        $stmt->execute([':o' => $orderId]);
        return $stmt->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC');
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public static function forSeller(int $sellerId): array
    {
        $sql = 'SELECT DISTINCT o.*
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE oi.seller_id = :s
                ORDER BY o.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':s' => $sellerId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM orders ORDER BY id DESC')
            ->fetchAll();
    }

    public static function transition(int $orderId, string $to, ?int $actorId, string $note = ''): bool
    {
        $pdo = Database::pdo();
        $order = self::find($orderId);
        if (!$order) {
            return false;
        }
        $from = (string)$order['status'];
        if (!Validator::isValidTransition($from, $to)) {
            return false;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE orders SET status = :s, updated_at = datetime("now") WHERE id = :id')
                ->execute([':s' => $to, ':id' => $orderId]);
            $pdo->prepare(
                'INSERT INTO order_events (order_id, actor_id, from_status, to_status, note) VALUES (:o, :a, :f, :t, :n)'
            )->execute([
                ':o' => $orderId,
                ':a' => $actorId,
                ':f' => $from,
                ':t' => $to,
                ':n' => $note,
            ]);
            if ($to === 'cancelled' || $to === 'refunded') {
                $items = self::items($orderId);
                $restock = $pdo->prepare(
                    'UPDATE inventory SET quantity = quantity + :q, updated_at = datetime("now") WHERE product_id = :p'
                );
                $event = $pdo->prepare(
                    'INSERT INTO inventory_events (product_id, delta, reason, actor_id) VALUES (:p, :d, :r, :a)'
                );
                foreach ($items as $it) {
                    $restock->execute([':q' => (int)$it['quantity'], ':p' => (int)$it['product_id']]);
                    $event->execute([
                        ':p' => (int)$it['product_id'],
                        ':d' => (int)$it['quantity'],
                        ':r' => $to . ' restock',
                        ':a' => $actorId,
                    ]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return true;
    }
}