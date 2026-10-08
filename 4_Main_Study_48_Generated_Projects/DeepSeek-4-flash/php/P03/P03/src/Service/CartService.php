<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\CartRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\SettingRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-05 Shopping cart: customers add, update and remove cart items.
 */
final class CartService
{
    public function __construct(
        private CartRepository $cart,
        private ProductRepository $products,
        private SettingRepository $settings
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>,totals:array<string,mixed>}
     */
    public function get(array $user): array
    {
        $items = $this->cart->items((int) $user['id']);
        return [
            'items' => $items,
            'totals' => $this->totals($items),
        ];
    }

    public function add(array $user, int $productId, int $quantity): array
    {
        $product = $this->products->byId($productId);
        if ($product === null) {
            throw new DomainException('Product not found or unavailable.', 404);
        }
        if ($quantity < 1 || $quantity > 100) {
            throw new ValidationException(['quantity' => 'Quantity must be between 1 and 100.']);
        }
        $current = $this->cart->byProduct((int) $user['id'], $productId);
        $desired = ($current['quantity'] ?? 0) + $quantity;
        if ($desired > (int) $product['stock']) {
            throw new DomainException("Only {$product['stock']} unit(s) of this product are in stock.", 422);
        }
        $this->cart->add((int) $user['id'], $productId, $quantity);
        return $this->get($user);
    }

    public function updateQuantity(array $user, int $cartId, int $quantity): array
    {
        $item = $this->cart->byItemId((int) $user['id'], $cartId);
        if ($item === null) {
            throw new DomainException('Cart item not found.', 404);
        }
        $product = $this->products->byId((int) $item['product_id'], true);
        if ($product === null) {
            throw new DomainException('Cart item not found.', 404);
        }
        if ($quantity < 1) {
            $this->cart->remove($cartId);
            return $this->get($user);
        }
        if ($quantity > 100) {
            throw new ValidationException(['quantity' => 'Quantity must be between 1 and 100.']);
        }
        if ($quantity > (int) $product['stock']) {
            throw new DomainException("Only {$product['stock']} unit(s) of this product are in stock.", 422);
        }
        $this->cart->updateQuantity($cartId, $quantity);
        return $this->get($user);
    }

    public function remove(array $user, int $cartId): array
    {
        $item = $this->cart->byItemId((int) $user['id'], $cartId);
        if ($item === null) {
            throw new DomainException('Cart item not found.', 404);
        }
        $this->cart->remove($cartId);
        return $this->get($user);
    }

    public function count(array $user): int
    {
        return $this->cart->count((int) $user['id']);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public function totals(array $items): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }
        $subtotal = round($subtotal, 2);
        $threshold = (float) $this->settings->get('free_shipping_threshold', '100');
        $shipping = ($subtotal >= $threshold || $subtotal == 0) ? 0.0 : (float) $this->settings->get('shipping_flat', '5.00');
        $tax = round($subtotal * (float) $this->settings->get('tax_rate', '0.08'), 2);
        $total = round($subtotal + $shipping + $tax, 2);
        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function settings(): array
    {
        return $this->settings->all();
    }
}
