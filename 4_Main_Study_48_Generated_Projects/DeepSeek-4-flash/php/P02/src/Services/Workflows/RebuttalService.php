<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\PaperSubmissionRepository;
use App\Repositories\RebuttalRepository;
use App\Repositories\ReviewerAssignmentRepository;
use App\Services\PhaseService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class RebuttalService implements WorkflowInterface
{
    public function __construct(
        private readonly RebuttalRepository $repo,
        private readonly PaperSubmissionRepository $submissions,
        private readonly ReviewerAssignmentRepository $assignments,
        private readonly PhaseService $phases,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if ($user['role'] === 'author') {
            return ['rebuttals' => $this->repo->forAuthor((int) $user['id'])];
        }
        if ($user['role'] === 'reviewer') {
            $out = [];
            foreach ($this->assignments->submissionIdsForReviewer((int) $user['id']) as $submissionId) {
                foreach ($this->repo->forSubmission($submissionId) as $rebuttal) {
                    $out[] = $rebuttal;
                }
            }
            return ['rebuttals' => $out];
        }
        if (RoleGuard::isChair($user)) {
            return ['rebuttals' => $this->repo->findAll([], 'id DESC', 300)];
        }
        return ['rebuttals' => []];
    }

    public function show(array $user, int $id): array
    {
        $rebuttal = $this->repo->getById($id);
        if ($rebuttal === null) {
            throw new WorkflowException('not_found', 404, [], 'Rebuttal not found.');
        }
        $this->assertCanView($user, $rebuttal);
        return $rebuttal;
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['author', 'chair', 'admin']);

        $errors = Validator::validate($input, ['submission_id' => 'required|int', 'text' => 'required']);
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }

        $submissionId = (int) $input['submission_id'];
        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $submission['author_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only rebut your own submissions.');
        }

        $phase = $this->phases->get('rebuttal');
        $inRebuttalPhase = $submission['status'] === 'in_rebuttal';
        $phaseOpen = $phase !== null && $phase['status'] === 'open';
        if (!$inRebuttalPhase && !$phaseOpen) {
            throw new WorkflowException('phase_error', 409, ['phase' => 'rebuttal'], 'Rebuttals are not currently allowed for this submission.');
        }

        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'author_id' => (int) $submission['author_id'],
            'text' => (string) $input['text'],
            'status' => 'submitted',
        ]);
        $this->realtime->publish('rebuttal.submitted', [
            'rebuttal_id' => $id,
            'submission_id' => $submissionId,
        ]);
        return $this->repo->getById($id);
    }

    public function update(array $user, int $id, array $input): array
    {
        $rebuttal = $this->repo->getById($id);
        if ($rebuttal === null) {
            throw new WorkflowException('not_found', 404, [], 'Rebuttal not found.');
        }

        if ($user['role'] === 'author') {
            if ((int) $rebuttal['author_id'] !== (int) $user['id']) {
                throw new WorkflowException('permission_error', 403, [], 'You may only update your own rebuttals.');
            }
            if (!array_key_exists('text', $input)) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            if (!in_array($rebuttal['status'], ['submitted', 'pending'], true)) {
                throw new WorkflowException('phase_error', 409, [], 'This rebuttal can no longer be edited.');
            }
            $this->repo->update($id, ['text' => (string) $input['text']]);
            return $this->repo->getById($id);
        }

        if (RoleGuard::isChair($user)) {
            $data = [];
            if (isset($input['status'])) {
                $status = (string) $input['status'];
                if (!in_array($status, ['submitted', 'pending', 'accepted', 'rejected'], true)) {
                    throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
                }
                $data['status'] = $status;
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $this->repo->update($id, $data);
            return $this->repo->getById($id);
        }

        throw new WorkflowException('permission_error', 403, [], 'You are not allowed to update rebuttals.');
    }

    private function assertCanView(array $user, array $rebuttal): void
    {
        if (RoleGuard::isChair($user)) {
            return;
        }
        if ((int) $rebuttal['author_id'] === (int) $user['id']) {
            return;
        }
        if ($user['role'] === 'reviewer') {
            $assignment = $this->assignments->findBySubmissionAndReviewer(
                (int) $rebuttal['submission_id'],
                (int) $user['id']
            );
            if ($assignment !== null) {
                return;
            }
        }
        throw new WorkflowException('permission_error', 403, [], 'This rebuttal is not visible to your account.');
    }
}
