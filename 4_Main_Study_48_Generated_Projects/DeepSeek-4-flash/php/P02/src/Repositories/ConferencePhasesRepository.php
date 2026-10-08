<?php

declare(strict_types=1);

namespace App\Repositories;

final class ConferencePhasesRepository extends BaseRepository
{
    public function table(): string
    {
        return 'conference_phases';
    }

    public function findByName(string $name): ?array
    {
        return $this->findOneBy('name', $name);
    }
}
