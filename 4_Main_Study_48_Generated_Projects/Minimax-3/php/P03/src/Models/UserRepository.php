<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class UserRepository
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE lower(email) = lower(:e)');
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $email, string $password, string $name, string $role): array
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, role) VALUES (:e, :h, :n, :r)'
        );
        $stmt->execute([':e' => $email, ':h' => $hash, ':n' => $name, ':r' => $role]);
        $id = (int)$pdo->lastInsertId();
        return self::find($id) ?? [];
    }

    public static function updateProfile(int $id, string $name, ?string $email = null): void
    {
        if ($email !== null) {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET display_name = :n, email = :e, updated_at = datetime("now") WHERE id = :id'
            );
            $stmt->execute([':n' => $name, ':e' => $email, ':id' => $id]);
        } else {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET display_name = :n, updated_at = datetime("now") WHERE id = :id'
            );
            $stmt->execute([':n' => $name, ':id' => $id]);
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    public static function list(): array
    {
        return Database::pdo()
            ->query('SELECT id, email, display_name, role, status FROM users ORDER BY id ASC')
            ->fetchAll();
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public static function createReset(int $userId): string
    {
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (:u, :t, :e)'
        );
        $stmt->execute([':u' => $userId, ':t' => $token, ':e' => $expires]);
        return $token;
    }

    public static function consumeReset(string $token, string $newPassword): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :t AND used_at IS NULL AND expires_at > datetime("now")');
        $stmt->execute([':t' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = datetime("now") WHERE id = :id')
                ->execute([':h' => $hash, ':id' => (int)$row['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at = datetime("now") WHERE id = :id')
                ->execute([':id' => (int)$row['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return true;
    }
}