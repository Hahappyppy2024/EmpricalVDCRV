<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\DecisionRepository;
use App\Repositories\PaperSubmissionRepository;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\PhaseService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class DecisionManagementService implements WorkflowInterface
{
    private const DECISION_TO_STATUS = [
        'accept' => 'accepted',
        'reject' => 'rejected',
        'major_revision' => 'decided',
        'minor_revision' => 'decided',
    ];

    public function __construct(
        private readonly DecisionRepository $repo,
        private readonly PaperSubmissionRepository $submissions,
        private readonly AuditService $audit,
        private readonly MailService $mail,
        private readonly PhaseService $phases,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if (RoleGuard::isChair($user)) {
            return ['decisions' => $this->repo->withDetails()];
        }
        if ($user['role'] === 'author') {
            $out = [];
            foreach ($this->submissions->search(['author_id' => (int) $user['id']]) as $submission) {
                foreach ($this->repo->forSubmission((int) $submission['id']) as $decision) {
                    $out[] = $decision;
                }
            }
            return ['decisions' => $out];
        }
        return ['decisions' => []];
    }

    public function show(array $user, int $id): array
    {
        $decision = $this->repo->getById($id);
        if ($decision === null) {
            throw new WorkflowException('not_found', 404, [], 'Decision not found.');
        }
        if ($user['role'] === 'author') {
            $submission = $this->submissions->getById((int) $decision['submission_id']);
            if ($submission === null || (int) $submission['author_id'] !== (int) $user['id']) {
                throw new WorkflowException('permission_error', 403, [], 'Decision is not visible to your account.');
            }
            return $decision;
        }
        RoleGuard::require($user, ['chair', 'admin']);
        return $decision;
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $this->phases->requireOpen('decision');

        $errors = Validator::validate(
            $input,
            [
                'submission_id' => 'required|int',
                'decision' => 'required|in:accept,reject,major_revision,minor_revision',
            ]
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }

        $submissionId = (int) $input['submission_id'];
        $submission = $this->submissions->getById($submissionId);
        if ($submission === null) {
            throw new WorkflowException('not_found', 404, [], 'Submission not found.');
        }
        if (in_array($submission['status'], ['accepted', 'rejected'], true)) {
            throw new WorkflowException('conflict_error', 409, [], 'This submission already has a final decision.');
        }

        $decision = (string) $input['decision'];
        $id = $this->repo->insert([
            'submission_id' => $submissionId,
            'decision' => $decision,
            'notification_text' => (string) ($input['notification_text'] ?? ''),
            'decided_by' => (int) $user['id'],
        ]);

        $newStatus = self::DECISION_TO_STATUS[$decision];
        $this->submissions->update($submissionId, ['status' => $newStatus]);

        $author = $this->submissions->withAuthor($submissionId);
        $decisionRow = $this->repo->getById($id);

        $this->audit->record(
            (int) $user['id'],
            'decision_recorded',
            'decision',
            $id,
            $decision . ' for submission ' . $submissionId
        );

        if ($author !== null) {
            $this->mail->send(
                $author['author_email'],
                'Decision for "' . $submission['title'] . '"',
                "Your submission was {$decision}. " . $decisionRow['notification_text']
            );
        }

        $this->realtime->publish('decision.recorded', [
            'decision_id' => $id,
            'submission_id' => $submissionId,
            'decision' => $decision,
        ]);

        return $decisionRow;
    }

    public function update(array $user, int $id, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $decision = $this->repo->getById($id);
        if ($decision === null) {
            throw new WorkflowException('not_found', 404, [], 'Decision not found.');
        }
        $data = [];
        if (isset($input['decision'])) {
            $decisionName = (string) $input['decision'];
            if (!in_array($decisionName, ['accept', 'reject', 'major_revision', 'minor_revision'], true)) {
                throw new WorkflowException('validation_error', 422, ['fields' => ['decision' => 'invalid decision']]);
            }
            $data['decision'] = $decisionName;
        }
        if (array_key_exists('notification_text', $input)) {
            $data['notification_text'] = (string) $input['notification_text'];
        }
        if ($data === []) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $this->repo->update($id, $data);
        return $this->repo->getById($id);
    }
}
