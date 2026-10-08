<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\ManuscriptAccessRepository;
use App\Repositories\PaperSubmissionRepository;
use App\Repositories\ReviewerAssignmentRepository;
use App\Repositories\StoredFileRepository;
use App\Services\RoleGuard;
use App\Services\WorkflowException;

final class ManuscriptAccessService implements WorkflowInterface
{
    public function __construct(
        private readonly ManuscriptAccessRepository $repo,
        private readonly PaperSubmissionRepository $submissions,
        private readonly StoredFileRepository $files,
        private readonly ReviewerAssignmentRepository $assignments
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if (RoleGuard::isChair($user)) {
            return ['records' => $this->repo->findAll([], 'id DESC', 200)];
        }
        return ['records' => $this->repo->findByUser((int) $user['id'])];
    }

    public function show(array $user, int $id): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Manuscript access record not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $record['accessed_by'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only view your own access records.');
        }
        return $record;
    }

    public function create(array $user, array $input): array
    {
        $submissionId = (int) ($input['submission_id'] ?? 0);
        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        $this->assertCanAccess($user, $submission);

        $fileId = isset($input['file_id']) ? (int) $input['file_id'] : null;
        $file = null;
        if ($fileId !== null) {
            $file = $this->files->getById($fileId);
            if ($file === null || (int) $file['submission_id'] !== $submissionId) {
                throw new WorkflowException('not_found', 404, [], 'Requested file does not belong to this submission.');
            }
        } else {
            $candidates = $this->files->findBySubmission($submissionId);
            foreach ($candidates as $candidate) {
                if ($candidate['kind'] === 'manuscript') {
                    $file = $candidate;
                    break;
                }
            }
        }
        if ($file === null) {
            throw new WorkflowException('not_found', 404, [], 'No manuscript file is available for this submission.');
        }

        $accessType = in_array((string) ($input['access_type'] ?? 'view'), ['view', 'download'], true)
            ? (string) $input['access_type']
            : 'view';

        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'file_id' => (int) $file['id'],
            'accessed_by' => (int) $user['id'],
            'access_type' => $accessType,
        ]);

        return [
            'record' => $this->repo->getById($id),
            'file' => $this->publicFile($file),
            'submission' => [
                'id' => $submissionId,
                'title' => $submission['title'],
                'status' => $submission['status'],
            ],
        ];
    }

    public function update(array $user, int $id, array $input): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Manuscript access record not found.');
        }
        if (!RoleGuard::isAdmin($user) && (int) $record['accessed_by'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only update your own access records.');
        }
        if (!isset($input['access_type'])) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $accessType = in_array((string) $input['access_type'], ['view', 'download'], true)
            ? (string) $input['access_type']
            : null;
        if ($accessType === null) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['access_type' => 'invalid type']]);
        }
        $this->repo->update($id, ['access_type' => $accessType]);
        return $this->repo->getById($id);
    }

    public function assertCanAccess(array $user, array $submission): void
    {
        if (RoleGuard::isChair($user)) {
            return;
        }
        if ((int) $submission['author_id'] === (int) $user['id']) {
            return;
        }
        $assignment = $this->assignments->findBySubmissionAndReviewer((int) $submission['id'], (int) $user['id']);
        if ($assignment !== null) {
            return;
        }
        throw new WorkflowException('permission_error', 403, [], 'You are not authorized to access this manuscript.');
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function publicFile(array $file): array
    {
        return [
            'id' => $file['id'],
            'name' => $file['name'],
            'mime' => $file['mime'],
            'size' => $file['size'],
            'path' => $file['path'],
            'kind' => $file['kind'],
        ];
    }
}
