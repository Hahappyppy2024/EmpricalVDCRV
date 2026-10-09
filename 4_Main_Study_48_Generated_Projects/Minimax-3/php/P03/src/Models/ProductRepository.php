<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;
use Shop\Support\Storage;

final class ProductRepository
{
    public static function categories(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM categories ORDER BY name ASC')
            ->fetchAll();
    }

    public static function findCategory(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE slug = :s');
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function bySku(string $sku): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM products WHERE sku = :s');
        $stmt->execute([':s' => $sku]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO products (sku, name, description, price_cents, currency, category_id, seller_id, status, image_url)
             VALUES (:sku, :n, :d, :p, :cur, :cid, :sid, :status, :img)'
        );
        $stmt->execute([
            ':sku' => $data['sku'],
            ':n' => $data['name'],
            ':d' => $data['description'],
            ':p' => $data['price_cents'],
            ':cur' => $data['currency'] ?? 'USD',
            ':cid' => $data['category_id'] ?? null,
            ':sid' => $data['seller_id'],
            ':status' => $data['status'] ?? 'draft',
            ':img' => $data['image_url'] ?? Storage::placeImage($data['sku']),
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO inventory (product_id, quantity) VALUES (:p, 0)')
            ->execute([':p' => $id]);
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        $map = [
            'name' => 'name',
            'description' => 'description',
            'price_cents' => 'price_cents',
            'category_id' => 'category_id',
            'status' => 'status',
            'image_url' => 'image_url',
        ];
        foreach ($map as $k => $col) {
            if (array_key_exists($k, $data)) {
                $fields[] = "$col = :$k";
                $params[":$k"] = $data[$k];
            }
        }
        if (!$fields) {
            return;
        }
        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ', updated_at = datetime("now") WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
    }

    public static function search(array $filters): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, u.display_name AS seller_name,
                       (SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id = p.id) AS stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.seller_id = u.id
                WHERE p.status = "published"';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (lower(p.name) LIKE :q OR lower(p.description) LIKE :q OR lower(p.sku) LIKE :q)';
            $params[':q'] = '%' . strtolower((string)$filters['q']) . '%';
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND c.slug = :cat';
            $params[':cat'] = (string)$filters['category'];
        }
        if (isset($filters['min_price_cents']) && is_numeric($filters['min_price_cents'])) {
            $sql .= ' AND p.price_cents >= :minp';
            $params[':minp'] = (int)$filters['min_price_cents'];
        }
        if (isset($filters['max_price_cents']) && is_numeric($filters['max_price_cents'])) {
            $sql .= ' AND p.price_cents <= :maxp';
            $params[':maxp'] = (int)$filters['max_price_cents'];
        }
        if (array_key_exists('in_stock', $filters)) {
            if ((string)$filters['in_stock'] === '1') {
                $sql .= ' AND (SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id = p.id) > 0';
            }
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 100';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function listForSeller(int $sellerId): array
    {
        $sql = 'SELECT p.*, c.name AS category_name,
                       (SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id = p.id) AS stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.seller_id = :sid
                ORDER BY p.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':sid' => $sellerId]);
        return $stmt->fetchAll();
    }
}