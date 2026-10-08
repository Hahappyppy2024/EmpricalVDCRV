<?php

declare(strict_types=1);

namespace App\Repositories;

final class StoredFileRepository extends BaseRepository
{
    public function table(): string
    {
        return 'stored_files';
    }

    public function findBySubmission(int $submissionId): array
    {
        return $this->findAll(['submission_id' => $submissionId], 'id DESC');
    }
}
