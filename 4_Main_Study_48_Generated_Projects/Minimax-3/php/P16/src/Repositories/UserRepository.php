<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class UserRepository
{
    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        return Connection::get()
            ->query('SELECT id, username, email, role, full_name, enabled, created_at FROM users ORDER BY username')
            ->fetchAll();
    }

    public function operators(): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id, username, full_name FROM users WHERE role = 'operator' AND enabled = 1 ORDER BY username"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(string $username, string $email, string $password, string $role, string $fullName): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Connection::get()->prepare(
            'INSERT INTO users (username, email, password_hash, role, full_name)
             VALUES (:u, :e, :p, :r, :n)'
        );
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => $hash,
            ':r' => $role,
            ':n' => $fullName,
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $stmt = Connection::get()->prepare('UPDATE users SET enabled = :en, updated_at = datetime(\'now\') WHERE id = :id');
        $stmt->execute([':en' => $enabled ? 1 : 0, ':id' => $id]);
    }

    public function setRole(int $id, string $role): void
    {
        $stmt = Connection::get()->prepare('UPDATE users SET role = :r, updated_at = datetime(\'now\') WHERE id = :id');
        $stmt->execute([':r' => $role, ':id' => $id]);
    }

    public function updatePassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = Connection::get()->prepare('UPDATE users SET password_hash = :p, updated_at = datetime(\'now\') WHERE id = :id');
        $stmt->execute([':p' => $hash, ':id' => $id]);
    }

    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);
        if (!$user || (int)$user['enabled'] !== 1) {
            return null;
        }
        if (!password_verify($password, (string)$user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public function delete(int $id): void
    {
        $stmt = Connection::get()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}