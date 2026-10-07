<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class CourseController
{
    public function __construct(
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function showCreateForm(): void
    {
        require_login($this->config, false);
        render('courses/new', ['title' => 'New Course', 'error' => null]);
    }

    public function createHtml(): void
    {
        verify_csrf(false);
        $userId = require_login($this->config, false);
        $result = $this->create(request_data(), $userId);
        if (!$result['success']) {
            render('courses/new', ['title' => 'New Course', 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $result['course']['course_id']);
    }

    public function createApi(): void
    {
        $userId = require_login($this->config, true);
        $result = $this->create(request_data(), $userId);
        if (!$result['success']) {
            json_response($result, 422);
        }
        json_response($result, 201);
    }

    private function create(array $data, int $userId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }

        $courseId = $this->courseRepo->create([
            'title' => $title,
            'description' => $description,
            'created_by' => $userId,
        ]);
        $this->membershipRepo->ensureMembership($courseId, $userId, 'teacher');

        return ['success' => true, 'course' => $this->courseRepo->findById($courseId)];
    }

    public function listHtml(): void
    {
        render('courses/index', ['title' => 'Courses', 'courses' => $this->courseRepo->all()]);
    }

    public function listApi(): void
    {
        json_response(['success' => true, 'courses' => $this->courseRepo->all()]);
    }

    public function getHtml(int $courseId): void
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            http_response_code(404);
            render('courses/show', ['title' => 'Course Not Found', 'course' => null]);
            return;
        }
        render('courses/show', [
            'title' => $course['title'],
            'course' => $course,
            'can_manage' => current_user_can_manage_course($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
        ]);
    }

    public function getApi(int $courseId): void
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            json_response(['success' => false, 'error' => 'Course not found'], 404);
        }
        json_response(['success' => true, 'course' => $course]);
    }

    public function showEditForm(int $courseId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            http_response_code(404);
            render('courses/show', ['title' => 'Course Not Found', 'course' => null]);
            return;
        }
        render('courses/edit', ['title' => 'Edit Course', 'course' => $course, 'error' => null]);
    }

    public function updateHtml(int $courseId): void
    {
        verify_csrf(false);
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->update($courseId, request_data());
        if (!$result['success']) {
            $course = $this->courseRepo->findById($courseId);
            render('courses/edit', ['title' => 'Edit Course', 'course' => $course, 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId);
    }

    public function updateApi(int $courseId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, request_data());
        if (!$result['success']) {
            $status = $result['error'] === 'Course not found' ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, array $data): array
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }

        $this->courseRepo->update($courseId, ['title' => $title, 'description' => $description]);
        return ['success' => true, 'course' => $this->courseRepo->findById($courseId)];
    }

    public function deleteHtml(int $courseId): void
    {
        verify_csrf(false);
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId);
        redirect_to('/courses');
    }

    public function deleteApi(int $courseId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId): array
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        $this->courseRepo->delete($courseId);
        return ['success' => true];
    }
}