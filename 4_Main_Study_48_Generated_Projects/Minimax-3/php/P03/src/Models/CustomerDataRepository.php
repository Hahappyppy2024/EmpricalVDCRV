<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class CustomerDataRepository
{
    public static function addresses(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id ASC');
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public static function ensurePreferences(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM customer_preferences WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $pdo->prepare('INSERT INTO customer_preferences (user_id) VALUES (:u)')
            ->execute([':u' => $userId]);
        $stmt->execute([':u' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public static function upsertPreferences(int $userId, array $data): void
    {
        $pdo = Database::pdo();
        $row = self::ensurePreferences($userId);
        $newsletter = !empty($data['newsletter']) ? 1 : 0;
        $marketing = !empty($data['marketing_opt_in']) ? 1 : 0;
        $currency = substr((string)($data['preferred_currency'] ?? 'USD'), 0, 8);
        $notes = substr((string)($data['notes'] ?? ''), 0, 1000);
        $pdo->prepare(
            'UPDATE customer_preferences
             SET newsletter = :n, marketing_opt_in = :m, preferred_currency = :c, notes = :nn, updated_at = datetime("now")
             WHERE user_id = :u'
        )->execute([
            ':n' => $newsletter,
            ':m' => $marketing,
            ':c' => $currency,
            ':nn' => $notes,
            ':u' => $userId,
        ]);
    }

    public static function addAddress(int $userId, array $data): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!empty($data['is_default'])) {
                $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :u')
                    ->execute([':u' => $userId]);
            }
            $stmt = $pdo->prepare(
                'INSERT INTO addresses (user_id, label, full_name, line1, line2, city, region, postal_code, country, is_default)
                 VALUES (:u, :l, :n, :l1, :l2, :c, :r, :p, :co, :def)'
            );
            $stmt->execute([
                ':u' => $userId,
                ':l' => substr((string)($data['label'] ?? 'home'), 0, 60),
                ':n' => (string)$data['full_name'],
                ':l1' => (string)$data['line1'],
                ':l2' => (string)($data['line2'] ?? ''),
                ':c' => (string)$data['city'],
                ':r' => (string)($data['region'] ?? ''),
                ':p' => (string)$data['postal_code'],
                ':co' => substr((string)($data['country'] ?? 'US'), 0, 8),
                ':def' => !empty($data['is_default']) ? 1 : 0,
            ]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteAddress(int $userId, int $addressId): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $addressId, ':u' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function defaultAddress(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id ASC LIMIT 1'
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}