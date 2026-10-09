<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;
use Shop\Support\Validator;

final class CartRepository
{
    public static function ensureCart(int $userId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
        $pdo->prepare('INSERT INTO carts (user_id) VALUES (:u)')->execute([':u' => $userId]);
        return (int)$pdo->lastInsertId();
    }

    public static function items(int $cartId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ci.*, p.name, p.image_url, p.sku,
                    (ci.unit_price_cents * ci.quantity) AS line_total
             FROM cart_items ci
             JOIN products p ON ci.product_id = p.id
             WHERE ci.cart_id = :c
             ORDER BY ci.id ASC'
        );
        $stmt->execute([':c' => $cartId]);
        return $stmt->fetchAll();
    }

    public static function add(int $cartId, int $productId, int $quantity): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, price_cents FROM products WHERE id = :p AND status = "published"');
            $stmt->execute([':p' => $productId]);
            $product = $stmt->fetch();
            if (!$product) {
                $pdo->rollBack();
                return false;
            }
            $qty = max(1, $quantity);
            $stmt = $pdo->prepare(
                'SELECT id, quantity FROM cart_items WHERE cart_id = :c AND product_id = :p'
            );
            $stmt->execute([':c' => $cartId, ':p' => $productId]);
            $existing = $stmt->fetch();
            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $pdo->prepare('UPDATE cart_items SET quantity = :q WHERE id = :id')
                    ->execute([':q' => $newQty, ':id' => (int)$existing['id']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, unit_price_cents) VALUES (:c, :p, :q, :up)'
                )->execute([
                    ':c' => $cartId,
                    ':p' => $productId,
                    ':q' => $qty,
                    ':up' => (int)$product['price_cents'],
                ]);
            }
            $pdo->prepare('UPDATE carts SET updated_at = datetime("now") WHERE id = :c')
                ->execute([':c' => $cartId]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateQuantity(int $cartId, int $itemId, int $quantity, int $userId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT ci.id FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE ci.id = :i AND c.user_id = :u'
        );
        $stmt->execute([':i' => $itemId, ':u' => $userId]);
        if (!$stmt->fetch()) {
            return false;
        }
        if ($quantity <= 0) {
            return self::remove($cartId, $itemId, $userId);
        }
        $pdo->prepare('UPDATE cart_items SET quantity = :q WHERE id = :i')
            ->execute([':q' => $quantity, ':i' => $itemId]);
        $pdo->prepare('UPDATE carts SET updated_at = datetime("now") WHERE id = :c')
            ->execute([':c' => $cartId]);
        return true;
    }

    public static function remove(int $cartId, int $itemId, int $userId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT ci.id FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE ci.id = :i AND c.user_id = :u'
        );
        $stmt->execute([':i' => $itemId, ':u' => $userId]);
        if (!$stmt->fetch()) {
            return false;
        }
        $pdo->prepare('DELETE FROM cart_items WHERE id = :i')->execute([':i' => $itemId]);
        $pdo->prepare('UPDATE carts SET updated_at = datetime("now") WHERE id = :c')
            ->execute([':c' => $cartId]);
        return true;
    }

    public static function clear(int $cartId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :c')->execute([':c' => $cartId]);
        $pdo->prepare('UPDATE carts SET promotion_id = NULL WHERE id = :c')->execute([':c' => $cartId]);
    }

    public static function totals(int $cartId): array
    {
        $items = self::items($cartId);
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += (int)$it['line_total'];
        }
        $promo = null;
        $stmt = Database::pdo()->prepare(
            'SELECT promotion_id FROM carts WHERE id = :c'
        );
        $stmt->execute([':c' => $cartId]);
        $promoId = $stmt->fetchColumn();
        if ($promoId) {
            $stmt = Database::pdo()->prepare('SELECT * FROM promotions WHERE id = :p AND active = 1');
            $stmt->execute([':p' => $promoId]);
            $promo = $stmt->fetch() ?: null;
        }
        $discount = 0;
        if ($promo) {
            $discount = (int)floor($subtotal * ((int)$promo['percent_off']) / 100);
        }
        return [
            'items' => $items,
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'total_cents' => $subtotal - $discount,
            'promotion' => $promo,
        ];
    }

    public static function applyPromotion(int $cartId, string $code): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM promotions WHERE lower(code) = lower(:c) AND active = 1');
        $stmt->execute([':c' => $code]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            return false;
        }
        $pdo->prepare('UPDATE carts SET promotion_id = :p WHERE id = :c')
            ->execute([':p' => (int)$id, ':c' => $cartId]);
        return true;
    }

    public static function cartForUser(int $userId): array
    {
        $id = self::ensureCart($userId);
        return self::totals($id);
    }
}