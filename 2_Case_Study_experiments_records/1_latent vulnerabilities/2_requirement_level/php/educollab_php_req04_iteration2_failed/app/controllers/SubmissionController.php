<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class SubmissionController
{
    public function __construct(
        private SubmissionRepository $submissionRepo,
        private AssignmentRepository $assignmentRepo,
        private UploadRepository $uploadRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    private function submissionUploadsForUser(int $courseId, int $userId): array
    {
        return $this->uploadRepo->listByCourseAndUploader($courseId, $userId);
    }

    private function findAuthorizedAttachment(int $courseId, int $uploadId, int $userId): ?array
    {
        return $this->uploadRepo->findByIdAndUploader($courseId, $uploadId, $userId);
    }

    public function showSubmitForm(int $courseId, int $assignmentId): void
    {
        $userId = require_login($this->config, false);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/assignments/submit', [
            'title' => 'Submit Assignment',
            'course' => $this->courseRepo->findById($courseId),
            'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId),
            'uploads' => $this->submissionUploadsForUser($courseId, $userId),
            'my_submissions' => array_values(array_filter(
                $this->submissionRepo->listByUser($userId),
                fn(array $s): bool => (int) $s['course_id'] === $courseId && (int) $s['assignment_id'] === $assignmentId
            )),
            'error' => null,
        ]);
    }

    public function createHtml(int $courseId, int $assignmentId): void
    {
        $userId = require_login($this->config, false);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->create($courseId, $assignmentId, $userId, request_data());
        if (!$result['success']) {
            render('courses/assignments/submit', [
                'title' => 'Submit Assignment',
                'course' => $this->courseRepo->findById($courseId),
                'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId),
                'uploads' => $this->submissionUploadsForUser($courseId, $userId),
                'my_submissions' => [],
                'error' => $result['error'],
            ]);
            return;
        }
        redirect_to('/my/submissions');
    }

    public function createApi(int $courseId, int $assignmentId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->create($courseId, $assignmentId, $userId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Assignment not found', 'Attachment not found or not allowed for this submission'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 201);
    }

    private function create(int $courseId, int $assignmentId, int $userId, array $data): array
    {
        if (!$this->assignmentRepo->findById($courseId, $assignmentId)) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }
        $contentText = trim((string) ($data['content_text'] ?? ''));
        $attachmentUploadId = isset($data['attachment_upload_id']) && $data['attachment_upload_id'] !== '' ? (int) $data['attachment_upload_id'] : null;
        if ($contentText === '' && $attachmentUploadId === null) {
            return ['success' => false, 'error' => 'content_text or attachment_upload_id is required'];
        }
        if ($attachmentUploadId !== null && !$this->findAuthorizedAttachment($courseId, $attachmentUploadId, $userId)) {
            return ['success' => false, 'error' => 'Attachment not found or not allowed for this submission'];
        }
        $submissionId = $this->submissionRepo->create([
            'assignment_id' => $assignmentId,
            'course_id' => $courseId,
            'student_id' => $userId,
            'content_text' => $contentText,
            'attachment_upload_id' => $attachmentUploadId,
        ]);
        return ['success' => true, 'submission' => $this->submissionRepo->findById($courseId, $assignmentId, $submissionId)];
    }

    public function updateHtml(int $courseId, int $assignmentId, int $submissionId): void
    {
        $userId = require_login($this->config, false);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->update($courseId, $assignmentId, $submissionId, $userId, request_data());
        redirect_to('/my/submissions');
    }

    public function updateApi(int $courseId, int $assignmentId, int $submissionId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $assignmentId, $submissionId, $userId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Submission not found', 'Attachment not found or not allowed for this submission'], true) ? 404 : ($result['error'] === 'Forbidden' ? 403 : 422);
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $assignmentId, int $submissionId, int $userId, array $data): array
    {
        $submission = $this->submissionRepo->findById($courseId, $assignmentId, $submissionId);
        if (!$submission) {
            return ['success' => false, 'error' => 'Submission not found'];
        }
        if ((int) $submission['student_id'] !== $userId) {
            return ['success' => false, 'error' => 'Forbidden'];
        }
        $contentText = trim((string) ($data['content_text'] ?? ''));
        $attachmentUploadId = isset($data['attachment_upload_id']) && $data['attachment_upload_id'] !== '' ? (int) $data['attachment_upload_id'] : null;
        if ($contentText === '' && $attachmentUploadId === null) {
            return ['success' => false, 'error' => 'content_text or attachment_upload_id is required'];
        }
        if ($attachmentUploadId !== null && !$this->findAuthorizedAttachment($courseId, $attachmentUploadId, $userId)) {
            return ['success' => false, 'error' => 'Attachment not found or not allowed for this submission'];
        }
        $this->submissionRepo->update($courseId, $assignmentId, $submissionId, [
            'content_text' => $contentText,
            'attachment_upload_id' => $attachmentUploadId,
        ]);
        return ['success' => true, 'submission' => $this->submissionRepo->findById($courseId, $assignmentId, $submissionId)];
    }

    public function listMyHtml(): void
    {
        $userId = require_login($this->config, false);
        render('my/submissions', ['title' => 'My Submissions', 'submissions' => $this->submissionRepo->listByUser($userId)]);
    }

    public function listMyApi(): void
    {
        $userId = require_login($this->config, true);
        json_response(['success' => true, 'submissions' => $this->submissionRepo->listByUser($userId)]);
    }

    public function listForAssignmentHtml(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/assignments/submissions', [
            'title' => 'Submissions',
            'course' => $this->courseRepo->findById($courseId),
            'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId),
            'submissions' => $this->submissionRepo->listByAssignment($courseId, $assignmentId),
        ]);
    }

    public function listForAssignmentApi(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'submissions' => $this->submissionRepo->listByAssignment($courseId, $assignmentId)]);
    }
}