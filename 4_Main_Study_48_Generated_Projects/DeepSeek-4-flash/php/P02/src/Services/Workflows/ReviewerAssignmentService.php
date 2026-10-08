<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\PaperSubmissionRepository;
use App\Repositories\ReviewerAssignmentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class ReviewerAssignmentService implements WorkflowInterface
{
    public function __construct(
        private readonly ReviewerAssignmentRepository $repo,
        private readonly PaperSubmissionRepository $submissions,
        private readonly UserRepository $users,
        private readonly AuditService $audit,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if ($user['role'] === 'reviewer') {
            return ['assignments' => $this->repo->forReviewer((int) $user['id'])];
        }
        if (RoleGuard::isChair($user)) {
            return ['assignments' => $this->allAssignments()];
        }
        return ['assignments' => []];
    }

    public function show(array $user, int $id): array
    {
        $assignment = $this->repo->getById($id);
        if ($assignment === null) {
            throw new WorkflowException('not_found', 404, [], 'Assignment not found.');
        }
        if ($user['role'] === 'reviewer' && (int) $assignment['reviewer_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only view your own assignments.');
        }
        if (in_array($user['role'], ['author'], true) && (int) $assignment['reviewer_id'] !== (int) $user['id']) {
            $submission = $this->submissions->getById((int) $assignment['submission_id']);
            if ($submission === null || (int) $submission['author_id'] !== (int) $user['id']) {
                throw new WorkflowException('permission_error', 403, [], 'Assignment is not visible to your account.');
            }
            return $this->authorView($assignment);
        }
        if (!RoleGuard::isChair($user) && $user['role'] !== 'reviewer') {
            throw new WorkflowException('permission_error', 403, [], 'Assignment is not visible to your account.');
        }
        return $assignment;
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);

        $errors = Validator::validate(
            $input,
            [
                'submission_id' => 'required|int',
                'reviewer_id' => 'required|int',
                'conflict_of_interest' => 'in:0,1',
            ]
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }

        $submissionId = (int) $input['submission_id'];
        $reviewerId = (int) $input['reviewer_id'];

        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        $reviewer = $this->users->getById($reviewerId);
        if ($reviewer === null || $reviewer['role'] !== 'reviewer') {
            throw new WorkflowException('validation_error', 422, ['fields' => ['reviewer_id' => 'must reference a reviewer account']]);
        }
        if ((int) $submission['author_id'] === $reviewerId) {
            throw new WorkflowException('conflict_error', 409, [], 'The author of a submission cannot be assigned as its reviewer.');
        }
        if ($this->repo->findBySubmissionAndReviewer($submissionId, $reviewerId) !== null) {
            throw new WorkflowException('conflict_error', 409, [], 'This reviewer is already assigned to the submission.');
        }

        $conflict = (int) ($input['conflict_of_interest'] ?? 0);
        $conflictNote = (string) ($input['conflict_note'] ?? '');

        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'reviewer_id' => $reviewerId,
            'assigned_by' => (int) $user['id'],
            'status' => 'pending',
            'conflict_of_interest' => $conflict,
            'conflict_note' => $conflictNote,
        ]);

        $assignment = $this->repo->getById($id);
        $this->audit->record((int) $user['id'], 'reviewer_assigned', 'reviewer_assignment', $id);
        $this->realtime->publish('reviewer.assigned', [
            'assignment_id' => $id,
            'submission_id' => $submissionId,
            'reviewer_id' => $reviewerId,
            'conflict' => $conflict,
        ]);
        return $assignment;
    }

    public function update(array $user, int $id, array $input): array
    {
        $assignment = $this->repo->getById($id);
        if ($assignment === null) {
            throw new WorkflowException('not_found', 404, [], 'Assignment not found.');
        }

        $data = [];
        if ($user['role'] === 'reviewer') {
            if ((int) $assignment['reviewer_id'] !== (int) $user['id']) {
                throw new WorkflowException('permission_error', 403, [], 'You may only update your own assignments.');
            }
            if (!isset($input['status'])) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $status = (string) $input['status'];
            if (!in_array($status, ['accepted', 'declined'], true)) {
                throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'reviewers may accept or decline']]);
            }
            $data['status'] = $status;
        } elseif (RoleGuard::isChair($user)) {
            if (isset($input['status'])) {
                $status = (string) $input['status'];
                if (!in_array($status, ['pending', 'accepted', 'declined', 'completed'], true)) {
                    throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
                }
                $data['status'] = $status;
            }
            if (array_key_exists('conflict_of_interest', $input)) {
                $data['conflict_of_interest'] = (int) $input['conflict_of_interest'] ? 1 : 0;
            }
            if (array_key_exists('conflict_note', $input)) {
                $data['conflict_note'] = (string) $input['conflict_note'];
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
        } else {
            throw new WorkflowException('permission_error', 403, [], 'You are not allowed to update assignments.');
        }

        $this->repo->update($id, $data);
        $assignment = $this->repo->getById($id);
        $this->audit->record((int) $user['id'], 'reviewer_assignment_updated', 'reviewer_assignment', $id);
        return $assignment;
    }

    private function allAssignments(): array
    {
        $rows = $this->repo->findAll([], 'id DESC', 300);
        return array_map(function (array $a): array {
            $submission = $this->submissions->getById((int) $a['submission_id']);
            $reviewer = $this->users->getById((int) $a['reviewer_id']);
            return array_merge($a, [
                'submission_title' => $submission['title'] ?? 'Unknown',
                'reviewer_name' => $reviewer['name'] ?? 'Unknown',
            ]);
        }, $rows);
    }

    private function authorView(array $assignment): array
    {
        unset($assignment['reviewer_id']);
        $assignment['reviewer_name'] = 'Anonymous reviewer';
        return $assignment;
    }
}
