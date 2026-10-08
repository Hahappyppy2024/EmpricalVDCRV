<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\AuditRepository;
use Shop\Repository\OrderRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\PromotionRepository;
use Shop\Repository\SettingRepository;
use Shop\Repository\UserRepository;
use Shop\Repository\StockRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-10 Seller and administrator operations: products, users, promotions
 * and platform settings. Every privileged operation is audited.
 */
final class AdminService
{
    private const ALLOWED_SETTINGS = ['shop_name', 'currency', 'shipping_flat', 'free_shipping_threshold', 'tax_rate'];

    public function __construct(
        private UserRepository $users,
        private PromotionRepository $promotions,
        private SettingRepository $settings,
        private AuditRepository $audit,
        private OrderRepository $orders,
        private ProductRepository $products,
        private StockRepository $stock
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboardStats(array $user): array
    {
        $orders = $this->orders->all();
        $counts = $this->orders->statusCounts();
        $totals = $this->orders->totals();

        $productRows = $this->products->search([], includeInactive: true, sellerId: $user['role'] === 'admin' ? null : (int) $user['id']);
        $productIds = array_column($productRows, 'id');
        $lowStock = array_filter($productRows, fn (array $p) => (int) $p['stock'] <= 3);

        $salesValue = 0.0;
        foreach ($orders as $order) {
            if (in_array($order['status'], ['paid', 'shipped', 'delivered'], true)) {
                $salesValue += (float) $order['total'];
            }
        }

        return [
            'orders' => [
                'total' => count($orders),
                'by_status' => $counts,
                'revenue' => round($salesValue, 2),
                'lifetime' => $totals,
            ],
            'products' => [
                'total' => count($productRows),
                'low_stock' => count($lowStock),
            ],
            'users' => $user['role'] === 'admin' ? [
                'total' => count($this->users->all()),
                'customers' => count($this->users->customers()),
            ] : null,
        ];
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $data
     */
    public function createPromotion(array $actor, array $data): array
    {
        $errors = Validation::required($data, 'code', 'amount');
        $amount = Validation::float($data['amount'] ?? null, 0, 1000000);
        if ($amount === null) {
            $errors['amount'] = 'Amount must be a valid number.';
        }
        $type = (string) ($data['discount_type'] ?? 'percent');
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $errors['discount_type'] = 'Discount type must be "percent" or "fixed".';
        }
        if ($type === 'percent' && $amount !== null && $amount > 100) {
            $errors['amount'] = 'Percent discount cannot exceed 100.';
        }
        Validation::throw($errors);

        $existing = $this->promotions->byCode((string) $data['code']);
        if ($existing !== null) {
            throw new DomainException('A promotion with this code already exists.', 409);
        }

        $id = $this->promotions->create([
            'code' => (string) $data['code'],
            'description' => trim((string) ($data['description'] ?? '')),
            'discount_type' => $type,
            'amount' => $amount,
            'active' => (string) ($data['active'] ?? '1') === '1',
            'starts_at' => trim((string) ($data['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($data['ends_at'] ?? '')) ?: null,
            'max_uses' => trim((string) ($data['max_uses'] ?? '')) === '' ? null : (int) $data['max_uses'],
        ]);
        $this->audit->log((int) $actor['id'], 'promotion.create', 'Promotion', $id, "Created promotion {$data['code']}");
        return $this->promotions->byId($id) ?? [];
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $data
     */
    public function updatePromotion(array $actor, int $id, array $data): array
    {
        $promo = $this->promotions->byId($id);
        if ($promo === null) {
            throw new DomainException('Promotion not found.', 404);
        }
        $errors = Validation::required($data, 'amount');
        $amount = Validation::float($data['amount'] ?? null, 0, 1000000);
        if ($amount === null) {
            $errors['amount'] = 'Amount must be a valid number.';
        }
        Validation::throw($errors);
        $this->promotions->update($id, [
            'description' => trim((string) ($data['description'] ?? $promo['description'])),
            'discount_type' => (string) ($data['discount_type'] ?? $promo['discount_type']),
            'amount' => $amount,
            'active' => (string) ($data['active'] ?? $promo['active']) === '1',
            'starts_at' => trim((string) ($data['starts_at'] ?? $promo['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($data['ends_at'] ?? $promo['ends_at'] ?? '')) ?: null,
            'max_uses' => (string) ($data['max_uses'] ?? $promo['max_uses'] ?? '') === '' ? null : (int) $data['max_uses'],
        ]);
        $this->audit->log((int) $actor['id'], 'promotion.update', 'Promotion', $id, "Updated promotion {$promo['code']}");
        return $this->promotions->byId($id) ?? [];
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function togglePromotion(array $actor, int $id): array
    {
        $promo = $this->promotions->byId($id);
        if ($promo === null) {
            throw new DomainException('Promotion not found.', 404);
        }
        $this->promotions->setActive($id, (int) $promo['active'] === 1 ? 0 : 1);
        $this->audit->log((int) $actor['id'], 'promotion.toggle', 'Promotion', $id, 'Toggled promotion activity');
        return $this->promotions->byId($id) ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function promotions(): array
    {
        return $this->promotions->all();
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $data
     */
    public function manageUser(array $actor, int $id, array $data): array
    {
        if ($actor['role'] !== 'admin') {
            throw new DomainException('Only administrators can manage users.', 403);
        }
        if ((int) $actor['id'] === $id) {
            throw new DomainException('You cannot change your own account here.', 422);
        }
        $user = $this->users->byId($id);
        if ($user === null) {
            throw new DomainException('User not found.', 404);
        }
        if (array_key_exists('active', $data)) {
            $this->users->setActive($id, $data['active'] ? 1 : 0);
        }
        if (array_key_exists('role', $data) && $data['role'] !== '') {
            $roleId = $this->users->roleId((string) $data['role']);
            if ($roleId === 0) {
                throw new ValidationException(['role' => 'Unknown role.']);
            }
            $this->users->setRole($id, $roleId);
        }
        $this->audit->log((int) $actor['id'], 'user.manage', 'User', $id, 'Managed user account');
        return $this->users->byId($id) ?? [];
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function setSetting(array $actor, string $key, string $value): array
    {
        if ($actor['role'] !== 'admin') {
            throw new DomainException('Only administrators can change settings.', 403);
        }
        if (!in_array($key, self::ALLOWED_SETTINGS, true)) {
            throw new ValidationException(['key' => 'Unknown or disallowed setting key.']);
        }
        if (trim($value) === '') {
            throw new ValidationException(['value' => 'Setting value is required.']);
        }
        $this->settings->set($key, trim($value));
        $this->audit->log((int) $actor['id'], 'setting.update', 'Setting', null, "Updated setting {$key}");
        return $this->settings->all();
    }

    /**
     * @return array<string,string>
     */
    public function allSettings(): array
    {
        return $this->settings->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function auditLog(): array
    {
        return $this->audit->recent();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function lowStockProducts(): array
    {
        return $this->stock->lowStock(3);
    }
}
