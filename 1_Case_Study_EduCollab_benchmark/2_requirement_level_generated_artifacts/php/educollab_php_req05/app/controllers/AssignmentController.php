<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class AssignmentController
{
    public function __construct(
        private AssignmentRepository $assignmentRepo,
        private SubmissionRepository $submissionRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function showCreateForm(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/assignments/new', ['title' => 'New Assignment', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function createHtml(int $courseId): void
    {
        $userId = require_login($this->config, false);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            render('courses/assignments/new', ['title' => 'New Assignment', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/assignments/' . $result['assignment']['assignment_id']);
    }

    public function createApi(int $courseId): void
    {
        $userId = require_login($this->config, true);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            json_response($result, $result['error'] === 'Course not found' ? 404 : 422);
        }
        json_response($result, 201);
    }

    private function create(int $courseId, int $userId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $dueAt = trim((string) ($data['due_at'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }
        $assignmentId = $this->assignmentRepo->create([
            'course_id' => $courseId,
            'created_by' => $userId,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt === '' ? null : $dueAt,
        ]);
        return ['success' => true, 'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId)];
    }

    public function listHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/assignments/index', [
            'title' => 'Assignments',
            'course' => $this->courseRepo->findById($courseId),
            'assignments' => $this->assignmentRepo->listByCourse($courseId),
            'is_staff' => current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
        ]);
    }

    public function listApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'assignments' => $this->assignmentRepo->listByCourse($courseId)]);
    }

    public function getHtml(int $courseId, int $assignmentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $isStaff = current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId);
        $assignment = $this->assignmentRepo->findById($courseId, $assignmentId);
        if (!$assignment) {
            http_response_code(404);
        }
        render('courses/assignments/show', [
            'title' => $assignment ? $assignment['title'] : 'Assignment Not Found',
            'course' => $this->courseRepo->findById($courseId),
            'assignment' => $assignment,
            'is_staff' => $isStaff,
            'submissions' => $assignment && $isStaff ? $this->submissionRepo->listByAssignment($courseId, $assignmentId) : [],
        ]);
    }

    public function getApi(int $courseId, int $assignmentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $isStaff = current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId);
        $assignment = $this->assignmentRepo->findById($courseId, $assignmentId);
        if (!$assignment) {
            json_response(['success' => false, 'error' => 'Assignment not found'], 404);
        }
        $payload = ['success' => true, 'assignment' => $assignment];
        if ($isStaff) {
            $payload['submissions'] = $this->submissionRepo->listByAssignment($courseId, $assignmentId);
        }
        json_response($payload);
    }

    public function showEditForm(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $assignment = $this->assignmentRepo->findById($courseId, $assignmentId);
        if (!$assignment) {
            http_response_code(404);
            render('courses/assignments/show', ['title' => 'Assignment Not Found', 'course' => $this->courseRepo->findById($courseId), 'assignment' => null, 'is_staff' => true, 'submissions' => []]);
            return;
        }
        render('courses/assignments/edit', ['title' => 'Edit Assignment', 'course' => $this->courseRepo->findById($courseId), 'assignment' => $assignment, 'error' => null]);
    }

    public function updateHtml(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->update($courseId, $assignmentId, request_data());
        if (!$result['success']) {
            render('courses/assignments/edit', ['title' => 'Edit Assignment', 'course' => $this->courseRepo->findById($courseId), 'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/assignments/' . $assignmentId);
    }

    public function updateApi(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $assignmentId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Assignment not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $assignmentId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        if (!$this->assignmentRepo->findById($courseId, $assignmentId)) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $dueAt = trim((string) ($data['due_at'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }
        $this->assignmentRepo->update($courseId, $assignmentId, [
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt === '' ? null : $dueAt,
        ]);
        return ['success' => true, 'assignment' => $this->assignmentRepo->findById($courseId, $assignmentId)];
    }

    public function deleteHtml(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId, $assignmentId);
        redirect_to('/courses/' . $courseId . '/assignments');
    }

    public function deleteApi(int $courseId, int $assignmentId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId, $assignmentId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $assignmentId): array
    {
        if (!$this->assignmentRepo->findById($courseId, $assignmentId)) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }
        $this->assignmentRepo->delete($courseId, $assignmentId);
        return ['success' => true];
    }
}
