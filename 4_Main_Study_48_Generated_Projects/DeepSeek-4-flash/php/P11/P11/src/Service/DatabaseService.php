<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\DatabaseRepository;

final class DatabaseService
{
    private DatabaseRepository $databases;

    private AuditRepository $audit;

    private QuotaService $quota;

    public function __construct()
    {
        $this->databases = new DatabaseRepository();
        $this->audit = new AuditRepository();
        $this->quota = new QuotaService();
    }

    public function listFor(array $user): array
    {
        return $this->databases->allForUser((int) $user['id']);
    }

    public function getForUser(int $id, array $user): ?array
    {
        $database = $this->databases->findForUser($id, (int) $user['id']);
        if ($database === null) {
            return null;
        }
        $database['users'] = $this->databases->usersFor($id);

        return $database;
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function create(array $user, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['Database name is required.', null];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return ['Database name may only contain letters, numbers and underscores.', null];
        }
        $limits = $this->quota->limits($user);
        if ($this->databases->countForUser((int) $user['id']) >= $limits['max_databases']) {
            return ['Database quota exceeded for your plan.', null];
        }
        if ($this->databases->nameExistsForUser($name, (int) $user['id'])) {
            return ['A database with this name already exists.', null];
        }

        $id = $this->databases->create((int) $user['id'], $name);
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'database_management', 'database', (string) $id, 'Created database ' . $name);

        return [null, $id];
    }

    public function delete(array $user, int $id): ?string
    {
        $database = $this->databases->findForUser($id, (int) $user['id']);
        if ($database === null) {
            return 'Unknown or out-of-scope database.';
        }
        $this->databases->delete($id);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'database_management', 'database', (string) $id, 'Deleted database ' . $database['name']);

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, userId]
     */
    public function addUser(array $user, int $databaseId, string $username, string $password, string $host, string $privileges): array
    {
        $database = $this->databases->findForUser($databaseId, (int) $user['id']);
        if ($database === null) {
            return ['Unknown or out-of-scope database.', null];
        }
        $username = trim($username);
        $password = (string) $password;
        if ($username === '') {
            return ['Database user name is required.', null];
        }
        if (strlen($password) < 8) {
            return ['Database user password must be at least 8 characters.', null];
        }
        $existing = $this->databases->usersFor($databaseId);
        foreach ($existing as $dbUser) {
            if (strcasecmp($dbUser['username'], $username) === 0) {
                return ['A database user with this name already exists.', null];
            }
        }

        $id = $this->databases->addUser($databaseId, $username, password_hash($password, PASSWORD_DEFAULT), $host !== '' ? $host : 'localhost', $privileges !== '' ? $privileges : 'ALL');
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'database_management', 'database_user', (string) $id, 'Added user ' . $username);

        return [null, $id];
    }

    public function deleteUser(array $user, int $databaseId, int $userId): ?string
    {
        $database = $this->databases->findForUser($databaseId, (int) $user['id']);
        if ($database === null) {
            return 'Unknown or out-of-scope database.';
        }
        $dbUser = $this->databases->findUser((int) $user['id'], $databaseId);
        if ($dbUser === null) {
            return 'Unknown database user.';
        }
        $this->databases->deleteUser($userId);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'database_management', 'database_user', (string) $userId, 'Removed user ' . $dbUser['username']);

        return null;
    }
}
