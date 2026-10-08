<?php

declare(strict_types=1);

namespace Shop\Repository;

final class UserRepository extends Repository
{
    public function byId(int $id): ?array
    {
        return $this->row(
            'SELECT u.*, r.code AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [$id]
        );
    }

    public function byEmail(string $email): ?array
    {
        return $this->row('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public function all(): array
    {
        return $this->rows(
            'SELECT u.*, r.code AS role FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id'
        );
    }

    public function customers(): array
    {
        return $this->rows(
            'SELECT u.*, r.code AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = ? ORDER BY u.id',
            ['customer']
        );
    }

    public function roleId(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = ?');
        $stmt->execute([$code]);
        return (int) $stmt->fetchColumn();
    }

    public function setActive(int $id, int $active): void
    {
        $this->exec('UPDATE users SET active = ? WHERE id = ?', [$active, $id]);
    }

    public function setRole(int $id, int $roleId): void
    {
        $this->exec('UPDATE users SET role_id = ? WHERE id = ?', [$roleId, $id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->exec('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateProfile(int $id, array $data): void
    {
        $existing = $this->byId($id) ?? [];

        $name = trim((string) ($data['name'] ?? $existing['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? $existing['phone'] ?? ''));

        $newsletter = (int) ($existing['newsletter'] ?? 0);
        if (array_key_exists('newsletter', $data)) {
            $newsletter = (string) $data['newsletter'] === '1' ? 1 : 0;
        }

        $preferences = json_decode((string) ($existing['preferences'] ?? '{}'), true) ?: [];
        if (isset($data['preferences']) && is_array($data['preferences'])) {
            foreach ($data['preferences'] as $key => $value) {
                if (is_array($value)) {
                    $preferences[$key] = $value;
                } else {
                    $preferences[$key] = array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) $value)
                    )));
                }
            }
        }

        $this->exec(
            'UPDATE users SET name = ?, phone = ?, newsletter = ?, preferences = ? WHERE id = ?',
            [$name, $phone, $newsletter, json_encode($preferences, JSON_UNESCAPED_SLASHES), $id]
        );
    }
}
