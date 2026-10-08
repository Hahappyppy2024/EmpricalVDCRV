<?php

declare(strict_types=1);

namespace App\Repositories;

final class RebuttalRepository extends BaseRepository
{
    public function table(): string
    {
        return 'rebuttals';
    }

    public function forSubmission(int $submissionId): array
    {
        return $this->findAll(['submission_id' => $submissionId], 'id DESC');
    }

    public function forAuthor(int $authorId): array
    {
        $st = $this->db->prepare(
            'SELECT rb.*, p.title AS submission_title FROM rebuttals rb'
            . ' JOIN paper_submissions p ON p.id = rb.submission_id'
            . ' WHERE rb.author_id = :aid ORDER BY rb.id DESC'
        );
        $st->execute(['aid' => $authorId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
