<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PlanRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM plans ORDER BY id')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE name = ?');
        $stmt->execute([$name]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO plans (name, disk_quota, bandwidth_quota, max_domains, max_sites, max_databases, monthly_price, created_at) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['name'],
            (int) $data['disk_quota'],
            (int) $data['bandwidth_quota'],
            (int) $data['max_domains'],
            (int) $data['max_sites'],
            (int) $data['max_databases'],
            (float) $data['monthly_price'],
            date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE plans SET name = ?, disk_quota = ?, bandwidth_quota = ?, max_domains = ?, max_sites = ?, max_databases = ?, monthly_price = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            (int) $data['disk_quota'],
            (int) $data['bandwidth_quota'],
            (int) $data['max_domains'],
            (int) $data['max_sites'],
            (int) $data['max_databases'],
            (float) $data['monthly_price'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('UPDATE users SET plan_id = NULL WHERE plan_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM plans WHERE id = ?')->execute([$id]);
    }
}
