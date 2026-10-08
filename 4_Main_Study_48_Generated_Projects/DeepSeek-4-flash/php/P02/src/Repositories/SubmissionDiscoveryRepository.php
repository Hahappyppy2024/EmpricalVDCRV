<?php

declare(strict_types=1);

namespace App\Repositories;

final class SubmissionDiscoveryRepository extends BaseRepository
{
    public function table(): string
    {
        return 'submission_discovery';
    }
}
