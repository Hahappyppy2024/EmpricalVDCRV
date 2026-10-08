<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\DoubleBlindViewsRepository;
use App\Repositories\PaperSubmissionRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\ReviewerAssignmentRepository;
use App\Services\RoleGuard;
use App\Services\WorkflowException;

final class DoubleBlindViewsService implements WorkflowInterface
{
    public function __construct(
        private readonly DoubleBlindViewsRepository $repo,
        private readonly PaperSubmissionRepository $submissions,
        private readonly ReviewRepository $reviews,
        private readonly ReviewerAssignmentRepository $assignments
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if (isset($query['submission_id']) && $query['submission_id'] !== '') {
            return $this->viewForSubmission($user, (int) $query['submission_id']);
        }
        $logs = RoleGuard::isChair($user)
            ? $this->repo->findAll([], 'id DESC', 200)
            : $this->repo->findAll(['viewer_id' => (int) $user['id']], 'id DESC', 200);
        return ['views' => $logs];
    }

    public function show(array $user, int $id): array
    {
        $log = $this->repo->getById($id);
        if ($log === null) {
            throw new WorkflowException('not_found', 404, [], 'Blind view record not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $log['viewer_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'This blind view record is not visible to your account.');
        }
        return $log;
    }

    public function create(array $user, array $input): array
    {
        $submissionId = (int) ($input['submission_id'] ?? 0);
        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }

        $blind = $this->blindFor($user, $submission);
        if (array_key_exists('blind', $input)) {
            $blind = (int) $input['blind'] ? 1 : 0;
        }

        $view = $this->buildView($user, $submission, $blind);
        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'viewer_id' => (int) $user['id'],
            'viewer_role' => (string) $user['role'],
            'blind' => $blind,
        ]);

        return [
            'record' => $this->repo->getById($id),
            'view' => $view,
        ];
    }

    public function update(array $user, int $id, array $input): array
    {
        $log = $this->repo->getById($id);
        if ($log === null) {
            throw new WorkflowException('not_found', 404, [], 'Blind view record not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $log['viewer_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only update your own blind view records.');
        }
        if (!array_key_exists('blind', $input)) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $this->repo->update($id, ['blind' => (int) $input['blind'] ? 1 : 0]);
        return $this->repo->getById($id);
    }

    public function viewForSubmission(array $user, int $submissionId): array
    {
        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        $blind = $this->blindFor($user, $submission);
        return ['view' => $this->buildView($user, $submission, $blind)];
    }

    /**
     * @param array<string, mixed> $submission
     */
    private function blindFor(array $user, array $submission): int
    {
        if ($user['role'] === 'reviewer') {
            return 1;
        }
        return 0;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $submission
     * @return array<string, mixed>
     */
    private function buildView(array $user, array $submission, int $blind): array
    {
        $base = [
            'id' => $submission['id'],
            'title' => $submission['title'],
            'abstract' => $submission['abstract'],
            'keywords' => $submission['keywords'],
            'status' => $submission['status'],
            'created_at' => $submission['created_at'],
            'blind' => $blind,
        ];

        if ($user['role'] === 'reviewer') {
            $base['author_name'] = 'Anonymous';
            $base['author_email'] = 'anonymous@blind.invalid';
            $base['reviewers_visible'] = false;
            return $base;
        }

        if ($user['role'] === 'author') {
            if ((int) $submission['author_id'] !== (int) $user['id'] && !RoleGuard::isChair($user)) {
                throw new WorkflowException('permission_error', 403, [], 'This submission is not visible to your account.');
            }
            $base['author_name'] = 'You';
            $base['reviewers_visible'] = false;
            $averages = $this->reviews->averages((int) $submission['id']);
            $base['review_summary'] = [
                'count' => (int) $averages['n'],
                'average_score' => $averages['avg_score'] !== null ? (float) $averages['avg_score'] : null,
                'average_confidence' => $averages['avg_confidence'] !== null ? (float) $averages['avg_confidence'] : null,
            ];
            $comments = [];
            foreach ($this->reviews->forSubmission((int) $submission['id']) as $review) {
                if ($review['status'] === 'submitted' && $review['comments'] !== '') {
                    $comments[] = ['comments' => $review['comments']];
                }
            }
            $base['review_comments'] = $comments;
            return $base;
        }

        $base['author_name'] = $submission['author_name'] ?? 'Unknown';
        $base['author_email'] = $submission['author_email'] ?? '';
        $base['reviewers_visible'] = true;
        $base['reviews'] = $this->reviews->forSubmission((int) $submission['id']);
        return $base;
    }
}
