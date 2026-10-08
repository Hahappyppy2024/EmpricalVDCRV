<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReviewerAssignmentRepository extends BaseRepository
{
    public function table(): string
    {
        return 'reviewer_assignments';
    }

    public function findBySubmissionAndReviewer(int $submissionId, int $reviewerId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM reviewer_assignments WHERE submission_id = :sid AND reviewer_id = :rid LIMIT 1'
        );
        $st->execute(['sid' => $submissionId, 'rid' => $reviewerId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function forReviewer(int $reviewerId): array
    {
        $st = $this->db->prepare(
            'SELECT ra.*, p.title AS submission_title, p.status AS submission_status,'
            .             ' u.name AS assigned_by_name, r.id AS review_id, r.score AS review_score, r.status AS review_status'
            . ' FROM reviewer_assignments ra'
            . ' JOIN paper_submissions p ON p.id = ra.submission_id'
            . ' JOIN users u ON u.id = ra.assigned_by'
            . ' LEFT JOIN reviews r ON r.assignment_id = ra.id'
            . ' WHERE ra.reviewer_id = :rid'
            . ' ORDER BY ra.created_at DESC'
        );
        $st->execute(['rid' => $reviewerId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forSubmission(int $submissionId): array
    {
        $st = $this->db->prepare(
            'SELECT ra.*, u.name AS reviewer_name, u.email AS reviewer_email'
            . ' FROM reviewer_assignments ra JOIN users u ON u.id = ra.reviewer_id'
            . ' WHERE ra.submission_id = :sid ORDER BY ra.id'
        );
        $st->execute(['sid' => $submissionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submissionIdsForReviewer(int $reviewerId): array
    {
        $rows = $this->findAll(['reviewer_id' => $reviewerId], 'id DESC');
        return array_map(static fn (array $r): int => (int) $r['submission_id'], $rows);
    }

    public function reviewerIdsForSubmission(int $submissionId): array
    {
        $rows = $this->forSubmission($submissionId);
        return array_map(static fn (array $r): int => (int) $r['reviewer_id'], $rows);
    }
}
