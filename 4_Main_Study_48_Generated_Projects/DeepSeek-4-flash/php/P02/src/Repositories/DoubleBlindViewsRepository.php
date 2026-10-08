<?php

declare(strict_types=1);

namespace App\Repositories;

final class DoubleBlindViewsRepository extends BaseRepository
{
    public function table(): string
    {
        return 'double_blind_views';
    }
}
