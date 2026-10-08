<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\BroadcastRepository;
use Shop\Repository\OrderRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\StockRepository;

/**
 * SHOP-07 Order access and SHOP-08 Order lifecycle.
 */
final class OrderService
{
    public const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => ['refunded'],
        'refunded' => [],
    ];

    public const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public function __construct(
        private OrderRepository $orders,
        private ProductRepository $products,
        private StockRepository $stock,
        private BroadcastRepository $broadcast
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(array $user, array $filters = []): array
    {
        if ($user['role'] === 'customer') {
            $filters['user_id'] = $user['id'];
            return $this->orders->forUser((int) $user['id'], $filters);
        }
        if ($user['role'] === 'seller') {
            return $this->orders->forSeller((int) $user['id'], $filters);
        }
        return $this->orders->all($filters);
    }

    public function detail(array $user, int $id): array
    {
        $order = $this->orders->byId($id);
        if ($order === null) {
            throw new DomainException('Order not found.', 404);
        }
        $this->assertCanView($user, $order);
        return $this->withDetail($order);
    }

    public function byNumber(array $user, string $number): array
    {
        $order = $this->orders->byNumber(trim($number));
        if ($order === null) {
            throw new DomainException('Order not found.', 404);
        }
        $this->assertCanView($user, $order);
        return $this->withDetail($order);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $order
     */
    private function assertCanView(array $user, array $order): void
    {
        if ($user['role'] === 'admin') {
            return;
        }
        if ($user['role'] === 'seller') {
            if (!$this->orders->hasSellerProduct((int) $order['id'], (int) $user['id'])) {
                throw new DomainException('Order not found.', 404);
            }
            return;
        }
        if ((int) $order['user_id'] !== (int) $user['id']) {
            throw new DomainException('Order not found.', 404);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function allowedTransitions(array $user, array $order): array
    {
        $privileged = in_array($user['role'], ['seller', 'admin'], true);
        $allowed = self::TRANSITIONS[$order['status']] ?? [];
        if (!$privileged) {
            // Customers may only cancel their own pending/paid orders.
            $allowed = in_array($order['status'], ['pending', 'paid'], true) ? ['cancelled'] : [];
        }
        return $allowed;
    }

    /**
     * @param array<string,mixed> $user
     */
    public function transition(array $user, int $id, string $to, string $comment = ''): array
    {
        $order = $this->orders->byId($id);
        if ($order === null) {
            throw new DomainException('Order not found.', 404);
        }
        $privileged = in_array($user['role'], ['seller', 'admin'], true);
        if ($user['role'] === 'seller' && !$this->orders->hasSellerProduct($id, (int) $user['id'])) {
            throw new DomainException('Order not found.', 404);
        }
        if (!$privileged && (int) $order['user_id'] !== (int) $user['id']) {
            throw new DomainException('You are not allowed to modify this order.', 403);
        }

        $current = (string) $order['status'];
        $allowed = self::TRANSITIONS[$current] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new DomainException("Order cannot transition from '{$current}' to '{$to}'.", 422);
        }
        if (!$privileged) {
            if ($to !== 'cancelled') {
                throw new DomainException('Customers may only cancel their own orders.', 403);
            }
            if (!in_array($current, ['pending', 'paid'], true)) {
                throw new DomainException('This order can no longer be cancelled.', 422);
            }
        }

        $this->orders->addHistory($id, $current, $to, (int) $user['id'], trim($comment));
        $this->orders->setStatus($id, $to);

        // Restock when an order is cancelled or refunded.
        if (in_array($to, ['cancelled', 'refunded'], true)) {
            $reason = $to === 'refunded' ? 'order_refunded' : 'order_cancelled';
            foreach ($this->orders->items($id) as $item) {
                if ($item['product_id'] === null) {
                    continue;
                }
                $delta = (int) $item['quantity'];
                $this->products->adjustStock((int) $item['product_id'], $delta);
                $this->stock->add((int) $item['product_id'], $delta, $reason, (int) $user['id']);
            }
        }

        $this->broadcast->add('order.status_changed', [
            'order_id' => (int) $order['id'],
            'number' => (string) $order['number'],
            'status' => $to,
            'previous' => $current,
        ]);

        return $this->detail($user, $id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function statusHistory(int $orderId): array
    {
        return $this->orders->history($orderId);
    }

    /**
     * @return array<string,mixed>
     */
    private function withDetail(array $order): array
    {
        $order['items'] = $this->orders->items((int) $order['id']);
        $order['history'] = $this->orders->history((int) $order['id']);
        $order['statuses'] = self::STATUSES;
        return $order;
    }
}
