<?php

declare(strict_types=1);

namespace Shop;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function register(string $email, string $password, string $name, string $role = 'customer'): array
    {
        $errors = Validation::required(['email' => $email, 'password' => $password, 'name' => $name], 'email', 'password', 'name');
        Validation::assertEmail($errors, ['email' => $email], 'email');
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        Validation::throw($errors);

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        if ($stmt->fetch() !== false) {
            throw new DomainException('An account with this email already exists.', 409);
        }

        $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = ?');
        $roleStmt->execute([$role]);
        $roleId = (int) $roleStmt->fetchColumn();

        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, name, role_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), trim($name), $roleId]);
        return $this->byId((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function login(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.code AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if ($user === false) {
            return null;
        }
        if ((int) $user['active'] !== 1) {
            throw new DomainException('This account has been disabled. Contact an administrator.', 403);
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public function byId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.code AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function roleId(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = ?');
        $stmt->execute([$code]);
        return (int) $stmt->fetchColumn();
    }
}
