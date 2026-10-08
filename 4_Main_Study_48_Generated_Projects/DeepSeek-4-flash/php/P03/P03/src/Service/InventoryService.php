<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\AuditRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\StockRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-09 Inventory: sellers manage stock counts and restock events.
 */
final class InventoryService
{
    public function __construct(
        private ProductRepository $products,
        private StockRepository $stock,
        private AuditRepository $audit
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(array $user, array $filters = []): array
    {
        $isAdmin = $user['role'] === 'admin';
        $items = $this->products->search($filters, includeInactive: true, sellerId: $isAdmin ? null : (int) $user['id']);
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function movements(array $user): array
    {
        return $this->stock->recent(200);
    }

    /**
     * @param array<string,mixed> $user
     */
    public function adjust(array $user, int $productId, int $delta, string $reason): array
    {
        $product = $this->products->byId($productId, true);
        if ($product === null) {
            throw new DomainException('Product not found.', 404);
        }
        if ($user['role'] !== 'admin' && (int) $user['id'] !== (int) $product['seller_id']) {
            throw new DomainException('You are not allowed to adjust this product\'s stock.', 403);
        }
        if ($delta === 0) {
            throw new ValidationException(['delta' => 'Adjustment cannot be zero.']);
        }
        if ($delta < 0 && ((int) $product['stock'] + $delta) < 0) {
            throw new DomainException('Stock cannot become negative.', 422);
        }
        if (mb_strlen(trim($reason)) < 2) {
            throw new ValidationException(['reason' => 'Reason is required.']);
        }
        $this->products->adjustStock($productId, $delta);
        $this->stock->add($productId, $delta, trim($reason), (int) $user['id']);
        $this->audit->log((int) $user['id'], 'inventory.adjust', 'Product', $productId, "Stock {$delta} ({$reason})");
        return $this->products->byId($productId, true) ?? [];
    }

    /**
     * @param array<string,mixed> $user
     */
    public function restock(array $user, int $productId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 1000000) {
            throw new ValidationException(['quantity' => 'Restock quantity must be between 1 and 1000000.']);
        }
        return $this->adjust($user, $productId, $quantity, 'restock');
    }
}
