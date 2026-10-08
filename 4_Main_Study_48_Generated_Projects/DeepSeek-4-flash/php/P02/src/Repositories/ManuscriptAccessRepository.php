<?php

declare(strict_types=1);

namespace App\Repositories;

final class ManuscriptAccessRepository extends BaseRepository
{
    public function table(): string
    {
        return 'manuscript_access';
    }

    public function findBySubmissionAndUser(int $submissionId, int $userId): array
    {
        return $this->findAll(['submission_id' => $submissionId, 'accessed_by' => $userId], 'id DESC', 50);
    }

    public function findByUser(int $userId): array
    {
        return $this->findAll(['accessed_by' => $userId], 'id DESC', 200);
    }
}
