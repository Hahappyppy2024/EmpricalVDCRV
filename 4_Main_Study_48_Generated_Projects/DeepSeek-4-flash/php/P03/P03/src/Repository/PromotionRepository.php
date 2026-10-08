<?php

declare(strict_types=1);

namespace Shop\Repository;

final class PromotionRepository extends Repository
{
    public function all(): array
    {
        return $this->rows('SELECT * FROM promotions ORDER BY id');
    }

    public function byId(int $id): ?array
    {
        return $this->row('SELECT * FROM promotions WHERE id = ?', [$id]);
    }

    public function byCode(string $code): ?array
    {
        return $this->row('SELECT * FROM promotions WHERE UPPER(code) = UPPER(?)', [$code]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $this->exec(
            'INSERT INTO promotions (code, description, discount_type, amount, active, starts_at, ends_at, max_uses) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                strtoupper($data['code']),
                $data['description'],
                $data['discount_type'],
                $data['amount'],
                $data['active'] ? 1 : 0,
                $data['starts_at'],
                $data['ends_at'],
                $data['max_uses'] !== null ? (int) $data['max_uses'] : null,
            ]
        );
        return $this->insertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->exec(
            'UPDATE promotions SET description = ?, discount_type = ?, amount = ?, active = ?, starts_at = ?, ends_at = ?, max_uses = ? WHERE id = ?',
            [
                $data['description'],
                $data['discount_type'],
                $data['amount'],
                $data['active'] ? 1 : 0,
                $data['starts_at'],
                $data['ends_at'],
                $data['max_uses'] !== null ? (int) $data['max_uses'] : null,
                $id,
            ]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->exec('UPDATE promotions SET active = ? WHERE id = ?', [$active, $id]);
    }

    public function incrementUses(int $id): void
    {
        $this->exec('UPDATE promotions SET uses = uses + 1 WHERE id = ?', [$id]);
    }

    public function valid(string $code): ?array
    {
        $promo = $this->byCode($code);
        if ($promo === null || (int) $promo['active'] !== 1) {
            return null;
        }
        if ($promo['starts_at'] !== null && $promo['starts_at'] > date('Y-m-d H:i:s')) {
            return null;
        }
        if ($promo['ends_at'] !== null && $promo['ends_at'] < date('Y-m-d H:i:s')) {
            return null;
        }
        if ($promo['max_uses'] !== null && (int) $promo['uses'] >= (int) $promo['max_uses']) {
            return null;
        }
        return $promo;
    }

    public function discountFor(array $promo, float $subtotal): float
    {
        if ($promo['discount_type'] === 'fixed') {
            return min((float) $promo['amount'], $subtotal);
        }
        return round($subtotal * ((float) $promo['amount'] / 100), 2);
    }
}
