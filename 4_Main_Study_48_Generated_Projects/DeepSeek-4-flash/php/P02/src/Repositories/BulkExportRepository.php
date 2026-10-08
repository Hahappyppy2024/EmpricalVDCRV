<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class BulkExportRepository extends BaseRepository
{
    public function table(): string
    {
        return 'bulk_exports';
    }

    public function withDetails(): array
    {
        $st = $this->db->prepare(
            'SELECT e.*, u.name AS requested_by_name FROM bulk_exports e'
            . ' JOIN users u ON u.id = e.requested_by ORDER BY e.id DESC'
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
