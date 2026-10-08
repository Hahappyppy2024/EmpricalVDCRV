<?php

declare(strict_types=1);

namespace App\Repositories;

final class DecisionRepository extends BaseRepository
{
    public function table(): string
    {
        return 'decisions';
    }

    public function forSubmission(int $submissionId): array
    {
        return $this->findAll(['submission_id' => $submissionId], 'decided_at DESC');
    }

    public function latestForSubmission(int $submissionId): ?array
    {
        $rows = $this->forSubmission($submissionId);
        return $rows[0] ?? null;
    }

    public function withDetails(): array
    {
        $st = $this->db->prepare(
            'SELECT d.*, p.title AS submission_title, u.name AS decided_by_name'
            . ' FROM decisions d'
            . ' JOIN paper_submissions p ON p.id = d.submission_id'
            . ' JOIN users u ON u.id = d.decided_by'
            . ' ORDER BY d.decided_at DESC'
        );
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
