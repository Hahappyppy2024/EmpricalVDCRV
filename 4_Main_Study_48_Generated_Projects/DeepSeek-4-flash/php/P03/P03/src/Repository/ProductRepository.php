<?php

declare(strict_types=1);

namespace Shop\Repository;

final class ProductRepository extends Repository
{
    /**
     * @param array<string,mixed> $filters
     */
    public function search(array $filters, bool $includeInactive = false, ?int $sellerId = null): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, u.name AS seller_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                JOIN users u ON u.id = p.seller_id';
        $where = [];
        $params = [];

        if (!$includeInactive) {
            $where[] = 'p.active = 1';
        }
        if ($sellerId !== null) {
            $where[] = 'p.seller_id = ?';
            $params[] = $sellerId;
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }
        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = 'p.price >= ?';
            $params[] = (float) $filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = 'p.price <= ?';
            $params[] = (float) $filters['max_price'];
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock > 0';
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.featured = 1';
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        $sql .= match ($sort) {
            'price_asc' => ' ORDER BY p.price ASC',
            'price_desc' => ' ORDER BY p.price DESC',
            'name' => ' ORDER BY p.name ASC',
            default => ' ORDER BY p.id DESC',
        };

        return $this->rows($sql, $params);
    }

    public function byId(int $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT p.*, c.name AS category_name, u.name AS seller_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                JOIN users u ON u.id = p.seller_id
                WHERE p.id = ?';
        if (!$includeInactive) {
            $sql .= ' AND p.active = 1';
        }
        return $this->row($sql, [$id]);
    }

    public function bySlug(string $slug, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT p.*, c.name AS category_name, u.name AS seller_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                JOIN users u ON u.id = p.seller_id
                WHERE p.slug = ?';
        if (!$includeInactive) {
            $sql .= ' AND p.active = 1';
        }
        return $this->row($sql, [$slug]);
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM products WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return $this->row($sql, $params) !== null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $this->exec(
            'INSERT INTO products (seller_id, category_id, name, slug, description, price, stock, image, active, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['seller_id'],
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['price'],
                $data['stock'],
                $data['image'] ?? '',
                $data['active'] ? 1 : 0,
                $data['featured'] ? 1 : 0,
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
            'UPDATE products SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, active = ?, featured = ?, image = COALESCE(?, image), updated_at = datetime(\'now\') WHERE id = ?',
            [
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['price'],
                $data['active'] ? 1 : 0,
                $data['featured'] ? 1 : 0,
                $data['image'],
                $id,
            ]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->exec('UPDATE products SET active = ?, updated_at = datetime(\'now\') WHERE id = ?', [$active, $id]);
    }

    public function setStock(int $id, int $stock): void
    {
        $this->exec('UPDATE products SET stock = ?, updated_at = datetime(\'now\') WHERE id = ?', [$stock, $id]);
    }

    public function adjustStock(int $id, int $delta): void
    {
        $this->exec('UPDATE products SET stock = stock + ?, updated_at = datetime(\'now\') WHERE id = ?', [$delta, $id]);
    }

    public function featured(): array
    {
        return $this->search(['featured' => 1]);
    }
}
