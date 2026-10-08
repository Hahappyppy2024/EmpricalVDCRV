<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    public function table(): string
    {
        return 'users';
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy('email', $email);
    }
}
