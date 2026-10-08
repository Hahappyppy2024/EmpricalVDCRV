<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\PaperSubmissionRepository;
use App\Repositories\StoredFileRepository;
use App\Services\FileService;
use App\Services\PhaseService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class PaperSubmissionService implements WorkflowInterface
{
    private const TRANSITIONS = [
        'submitted' => ['under_review'],
        'under_review' => ['in_rebuttal', 'accepted', 'rejected', 'decided'],
        'in_rebuttal' => ['under_review', 'accepted', 'rejected', 'decided'],
        'decided' => ['accepted', 'rejected'],
        'accepted' => [],
        'rejected' => [],
    ];

    public function __construct(
        private readonly PaperSubmissionRepository $repo,
        private readonly StoredFileRepository $files,
        private readonly FileService $fileService,
        private readonly PhaseService $phases,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        return ['submissions' => $this->visibleSubmissions($user, $query)];
    }

    public function show(array $user, int $id): array
    {
        $submission = $this->repo->withAuthor($id);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        $this->assertCanView($user, $submission);
        return $this->decorate($submission, $user);
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['author', 'chair', 'admin']);
        $this->phases->requireOpen('submission');

        $input = Validator::trimArray($input);
        $errors = Validator::validate(
            $input,
            ['title' => 'required', 'abstract' => 'required', 'keywords' => 'string']
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }

        $id = $this->repo->insert([
            'author_id' => (int) $user['id'],
            'title' => (string) $input['title'],
            'abstract' => (string) $input['abstract'],
            'keywords' => (string) ($input['keywords'] ?? ''),
            'manuscript_path' => null,
            'status' => 'submitted',
        ]);

        $uploaded = $input['uploaded_files']['manuscript'] ?? null;
        if ($uploaded !== null) {
            $file = $this->fileService->storeUpload($uploaded, $id, 'manuscript');
            $this->repo->update($id, ['manuscript_path' => $file['path']]);
        }

        $this->realtime->publish('paper.submitted', ['submission_id' => $id, 'author_id' => (int) $user['id']]);
        return $this->show($user, $id);
    }

    public function update(array $user, int $id, array $input): array
    {
        $submission = $this->repo->getById($id);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }

        if (RoleGuard::isChair($user)) {
            $data = [];
            if (isset($input['status'])) {
                $newStatus = (string) $input['status'];
                if (!isset(self::TRANSITIONS[$submission['status']]) || !in_array($newStatus, self::TRANSITIONS[$submission['status']], true)) {
                    throw new WorkflowException('phase_error', 409, ['from' => $submission['status'], 'to' => $newStatus], 'Invalid state transition.');
                }
                $data['status'] = $newStatus;
            }
            foreach (['title', 'abstract', 'keywords'] as $field) {
                if (array_key_exists($field, $input)) {
                    $data[$field] = (string) $input[$field];
                }
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $this->repo->update($id, $data);
        } elseif ((int) $submission['author_id'] === (int) $user['id']) {
            $data = [];
            foreach (['title', 'abstract', 'keywords'] as $field) {
                if (array_key_exists($field, $input)) {
                    $data[$field] = (string) $input[$field];
                }
            }
            if ($data === []) {
                throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
            }
            $this->repo->update($id, $data);
        } else {
            throw new WorkflowException('permission_error', 403, [], 'Only the author or a chair may update this submission.');
        }

        return $this->show($user, $id);
    }

    public function assertCanView(array $user, array $submission): void
    {
        if (RoleGuard::isChair($user)) {
            return;
        }
        if ((int) $submission['author_id'] === (int) $user['id']) {
            return;
        }
        if ($submission['status'] === 'accepted') {
            return;
        }
        throw new WorkflowException('permission_error', 403, [], 'This submission is not visible to your account.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visibleSubmissions(array $user, array $query): array
    {
        $submissions = $this->repo->search([
            'q' => (string) ($query['q'] ?? ''),
            'status' => (string) ($query['status'] ?? ''),
        ], 500);

        return array_values(array_filter(
            $submissions,
            fn (array $s): bool => $this->isVisible($user, $s)
        ));
    }

    private function isVisible(array $user, array $submission): bool
    {
        if (RoleGuard::isChair($user)) {
            return true;
        }
        if ((int) $submission['author_id'] === (int) $user['id']) {
            return true;
        }
        return $submission['status'] === 'accepted';
    }

    /**
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function decorate(array $submission, array $user): array
    {
        $submission['manuscript'] = $this->files->findBySubmission((int) $submission['id']);
        $submission['can_view'] = true;
        return $submission;
    }
}
