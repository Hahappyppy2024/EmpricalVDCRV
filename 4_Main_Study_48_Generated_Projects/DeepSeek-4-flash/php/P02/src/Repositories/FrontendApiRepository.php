<?php

declare(strict_types=1);

namespace App\Repositories;

final class FrontendApiRepository extends BaseRepository
{
    public function table(): string
    {
        return 'frontend_api_integration_and_errors';
    }
}
