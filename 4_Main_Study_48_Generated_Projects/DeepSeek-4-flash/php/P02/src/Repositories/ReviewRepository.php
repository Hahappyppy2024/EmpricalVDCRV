<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReviewRepository extends BaseRepository
{
    public function table(): string
    {
        return 'reviews';
    }

    public function findBySubmissionAndReviewer(int $submissionId, int $reviewerId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM reviews WHERE submission_id = :sid AND reviewer_id = :rid LIMIT 1');
        $st->execute(['sid' => $submissionId, 'rid' => $reviewerId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function forSubmission(int $submissionId): array
    {
        $st = $this->db->prepare(
            'SELECT r.*, u.name AS reviewer_name FROM reviews r'
            . ' JOIN users u ON u.id = r.reviewer_id WHERE r.submission_id = :sid ORDER BY r.id'
        );
        $st->execute(['sid' => $submissionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function averages(int $submissionId): array
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) AS n, ROUND(AVG(score), 2) AS avg_score, ROUND(AVG(confidence), 2) AS avg_confidence'
            . ' FROM reviews WHERE submission_id = :sid AND status = \'submitted\''
        );
        $st->execute(['sid' => $submissionId]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }
}
