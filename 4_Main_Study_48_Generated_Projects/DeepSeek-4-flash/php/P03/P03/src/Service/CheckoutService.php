<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\PaymentService;
use Shop\Repository\AddressRepository;
use Shop\Repository\BroadcastRepository;
use Shop\Repository\CartRepository;
use Shop\Repository\OrderRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\PromotionRepository;
use Shop\Repository\SettingRepository;
use Shop\Repository\StockRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-06 Checkout: shipping and simulated payment produce an order.
 * SHOP-13 Frontend API integration relies on this service's deterministic
 * payment simulation and the broadcast events it publishes.
 */
final class CheckoutService
{
    public function __construct(
        private CartRepository $cart,
        private AddressRepository $addresses,
        private OrderRepository $orders,
        private StockRepository $stock,
        private ProductRepository $products,
        private PromotionRepository $promotions,
        private SettingRepository $settings,
        private PaymentService $payment,
        private BroadcastRepository $broadcast,
        private CartService $cartService
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function data(array $user): array
    {
        return [
            'items' => $this->cart->items((int) $user['id']),
            'totals' => $this->cartService->totals($this->cart->items((int) $user['id'])),
            'addresses' => $this->addresses->forUser((int) $user['id']),
            'settings' => $this->settings->all(),
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     * @return array<string,mixed> the created order
     */
    public function placeOrder(array $user, array $data): array
    {
        $items = $this->cart->items((int) $user['id']);
        if ($items === []) {
            throw new DomainException('Your cart is empty.', 422);
        }

        foreach ($items as $item) {
            if ((int) $item['stock'] <= 0) {
                throw new DomainException("{$item['name']} is out of stock.", 422);
            }
            if ((int) $item['quantity'] > (int) $item['stock']) {
                throw new DomainException("Only {$item['stock']} unit(s) of {$item['name']} are in stock.", 422);
            }
        }

        $addressId = $this->resolveAddress($user, $data);
        $totals = $this->cartService->totals($items);

        $promotionId = null;
        $discount = 0.0;
        $promoCode = trim((string) ($data['promotion_code'] ?? ''));
        if ($promoCode !== '') {
            $promo = $this->promotions->valid($promoCode);
            if ($promo === null) {
                throw new DomainException('Promotion code is invalid, expired or has reached its usage limit.', 422);
            }
            $promotionId = (int) $promo['id'];
            $discount = $this->promotions->discountFor($promo, $totals['subtotal']);
            $totals['total'] = round($totals['total'] - $discount, 2);
            $totals['discount'] = $discount;
        } else {
            $totals['discount'] = 0.0;
        }

        $number = $this->orders->nextNumber();
        $orderId = $this->orders->create([
            'number' => $number,
            'user_id' => (int) $user['id'],
            'address_id' => $addressId,
            'status' => 'pending',
            'subtotal' => $totals['subtotal'],
            'shipping' => $totals['shipping'],
            'tax' => $totals['tax'],
            'discount' => $discount,
            'total' => $totals['total'],
            'payment_method' => (string) ($data['payment_method'] ?? 'card'),
            'payment_reference' => '',
            'promotion_id' => $promotionId,
        ]);

        foreach ($items as $item) {
            $line = round((float) $item['price'] * (int) $item['quantity'], 2);
            $this->orders->addItem($orderId, (int) $item['id'], (string) $item['name'], (float) $item['price'], (int) $item['quantity'], $line);
        }

        $order = $this->orders->byId($orderId);
        if ($order === null) {
            $this->orders->delete($orderId);
            throw new DomainException('Order could not be created.', 500);
        }

        // Simulated payment (deterministic local adapter).
        $charge = $this->payment->charge($order, $data);
        if (!$charge['success']) {
            $this->orders->delete($orderId);
            throw new DomainException($charge['message'], 402);
        }

        // Confirm payment: pending -> paid.
        $this->orders->addHistory($orderId, 'pending', 'paid', (int) $user['id'], 'Payment approved');
        $this->orders->setStatus($orderId, 'paid', $charge['reference']);

        // Decrement stock and record movements.
        foreach ($items as $item) {
            $delta = -1 * (int) $item['quantity'];
            $this->products->adjustStock((int) $item['id'], $delta);
            $this->stock->add((int) $item['id'], $delta, 'order', (int) $user['id']);
        }

        if ($promotionId !== null) {
            $this->promotions->incrementUses($promotionId);
        }

        $this->cart->clear((int) $user['id']);

        $order = $this->orders->byId($orderId) ?? $order;
        $this->broadcast->add('order.created', [
            'order_id' => (int) $order['id'],
            'number' => (string) $order['number'],
            'status' => (string) $order['status'],
            'total' => (float) $order['total'],
            'customer' => $user['name'],
        ]);
        $this->broadcast->add('order.status_changed', [
            'order_id' => (int) $order['id'],
            'number' => (string) $order['number'],
            'status' => (string) $order['status'],
            'previous' => 'pending',
        ]);

        return $order;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public function updateShippingAddress(array $user, int $orderId, array $data): array
    {
        $order = $this->orders->byId($orderId);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            throw new DomainException('Order not found.', 404);
        }
        $addressId = $this->resolveAddress($user, $data);
        $this->orders->setAddress($orderId, $addressId);
        return $this->orders->byId($orderId) ?? [];
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    private function resolveAddress(array $user, array $data): int
    {
        if (!empty($data['address_id'])) {
            $address = $this->addresses->byId((int) $data['address_id']);
            if ($address === null || (int) $address['user_id'] !== (int) $user['id']) {
                throw new DomainException('Invalid shipping address.', 422);
            }
            return (int) $address['id'];
        }
        $errors = Validation::required($data, 'line1', 'city', 'postal_code');
        Validation::throw($errors);
        return $this->addresses->create((int) $user['id'], $data);
    }
}
