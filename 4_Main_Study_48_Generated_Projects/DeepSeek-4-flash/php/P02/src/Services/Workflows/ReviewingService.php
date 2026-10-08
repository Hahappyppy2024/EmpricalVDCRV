<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\PaperSubmissionRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\ReviewerAssignmentRepository;
use App\Services\PhaseService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class ReviewingService implements WorkflowInterface
{
    public function __construct(
        private readonly ReviewRepository $repo,
        private readonly ReviewerAssignmentRepository $assignments,
        private readonly PaperSubmissionRepository $submissions,
        private readonly PhaseService $phases,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if ($user['role'] === 'reviewer') {
            return ['reviews' => $this->forReviewer((int) $user['id'])];
        }
        if (RoleGuard::isChair($user)) {
            return ['reviews' => $this->repo->findAll([], 'id DESC', 300)];
        }
        if ($user['role'] === 'author') {
            $reviews = [];
            foreach ($this->submissions->search(['author_id' => (int) $user['id']]) as $submission) {
                foreach ($this->repo->forSubmission((int) $submission['id']) as $review) {
                    $reviews[] = $this->authorView($review);
                }
            }
            return ['reviews' => $reviews];
        }
        return ['reviews' => []];
    }

    public function show(array $user, int $id): array
    {
        $review = $this->repo->getById($id);
        if ($review === null) {
            throw new WorkflowException('not_found', 404, [], 'Review not found.');
        }
        if ($user['role'] === 'reviewer' && (int) $review['reviewer_id'] === (int) $user['id']) {
            return $review;
        }
        if ($user['role'] === 'author') {
            $submission = $this->submissions->getById((int) $review['submission_id']);
            if ($submission !== null && (int) $submission['author_id'] === (int) $user['id']) {
                return $this->authorView($review);
            }
        }
        if (RoleGuard::isChair($user)) {
            return $review;
        }
        throw new WorkflowException('permission_error', 403, [], 'Review is not visible to your account.');
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['reviewer']);
        $this->phases->requireOpen('review');

        $errors = Validator::validate(
            $input,
            ['submission_id' => 'required|int', 'score' => 'required|int', 'confidence' => 'required|int']
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }

        $submissionId = (int) $input['submission_id'];
        $score = (int) $input['score'];
        $confidence = (int) $input['confidence'];

        if ($score < 1 || $score > 10) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['score' => 'score must be between 1 and 10']]);
        }
        if ($confidence < 1 || $confidence > 5) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['confidence' => 'confidence must be between 1 and 5']]);
        }

        $assignment = $this->assignments->findBySubmissionAndReviewer($submissionId, (int) $user['id']);
        if ($assignment === null) {
            throw new WorkflowException('permission_error', 403, [], 'You are not assigned to review this submission.');
        }
        if ($assignment['status'] === 'declined') {
            throw new WorkflowException('phase_error', 409, [], 'You declined this assignment and cannot submit a review.');
        }

        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        if ($submission['status'] === 'accepted' || $submission['status'] === 'rejected') {
            throw new WorkflowException('phase_error', 409, [], 'This submission has already been decided.');
        }
        if ($this->repo->findBySubmissionAndReviewer($submissionId, (int) $user['id']) !== null) {
            throw new WorkflowException('conflict_error', 409, [], 'You have already submitted a review for this submission.');
        }

        $status = ($input['status'] ?? 'draft') === 'submitted' ? 'submitted' : 'draft';
        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'reviewer_id' => (int) $user['id'],
            'assignment_id' => (int) $assignment['id'],
            'score' => $score,
            'confidence' => $confidence,
            'comments' => (string) ($input['comments'] ?? ''),
            'private_notes' => (string) ($input['private_notes'] ?? ''),
            'status' => $status,
        ]);

        $review = $this->repo->getById($id);
        if ($status === 'submitted') {
            $this->realtime->publish('review.submitted', [
                'review_id' => $id,
                'submission_id' => $submissionId,
                'reviewer_id' => (int) $user['id'],
            ]);
        }
        return $review;
    }

    public function update(array $user, int $id, array $input): array
    {
        $review = $this->repo->getById($id);
        if ($review === null) {
            throw new WorkflowException('not_found', 404, [], 'Review not found.');
        }

        if ($user['role'] === 'reviewer') {
            if ((int) $review['reviewer_id'] !== (int) $user['id']) {
                throw new WorkflowException('permission_error', 403, [], 'You may only update your own reviews.');
            }
            $data = [];
            if (array_key_exists('comments', $input)) {
                $data['comments'] = (string) $input['comments'];
            }
            if (array_key_exists('private_notes', $input)) {
                $data['private_notes'] = (string) $input['private_notes'];
            }
            if (array_key_exists('score', $input)) {
                $score = (int) $input['score'];
                if ($score < 1 || $score > 10) {
                    throw new WorkflowException('validation_error', 422, ['fields' => ['score' => 'score must be between 1 and 10']]);
                }
                $data['score'] = $score;
            }
            if (array_key_exists('confidence', $input)) {
                $confidence = (int) $input['confidence'];
                if ($confidence < 1 || $confidence > 5) {
                    throw new WorkflowException('validation_error', 422, ['fields' => ['confidence' => 'confidence must be between 1 and 5']]);
                }
                $data['confidence'] = $confidence;
            }
            if (isset($input['status']) && (string) $input['status'] === 'submitted' && $review['status'] === 'draft') {
                $data['status'] = 'submitted';
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->repo->update($id, $data);
            if (isset($data['status']) && $data['status'] === 'submitted') {
                $this->realtime->publish('review.submitted', [
                    'review_id' => $id,
                    'submission_id' => (int) $review['submission_id'],
                    'reviewer_id' => (int) $user['id'],
                ]);
            }
            return $this->repo->getById($id);
        }

        if (RoleGuard::isChair($user)) {
            $data = [];
            if (isset($input['status'])) {
                $data['status'] = (string) $input['status'] === 'submitted' ? 'submitted' : 'draft';
            }
            if (array_key_exists('score', $input)) {
                $data['score'] = (int) $input['score'];
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $this->repo->update($id, $data);
            return $this->repo->getById($id);
        }

        throw new WorkflowException('permission_error', 403, [], 'You are not allowed to update reviews.');
    }

    private function forReviewer(int $reviewerId): array
    {
        $out = [];
        foreach ($this->repo->findAll(['reviewer_id' => $reviewerId], 'id DESC') as $review) {
            $submission = $this->submissions->getById((int) $review['submission_id']);
            $out[] = array_merge($review, ['submission_title' => $submission['title'] ?? 'Unknown']);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private function authorView(array $review): array
    {
        unset($review['reviewer_id'], $review['private_notes']);
        $review['reviewer_name'] = 'Anonymous reviewer';
        return $review;
    }
}
