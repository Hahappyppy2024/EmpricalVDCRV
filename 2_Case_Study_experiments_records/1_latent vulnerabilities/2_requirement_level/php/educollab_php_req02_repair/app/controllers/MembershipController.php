<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class MembershipController
{
    public function __construct(
        private MembershipRepository $membershipRepo,
        private CourseRepository $courseRepo,
        private UserRepository $userRepo,
        private array $config
    ) {
    }

    public function showAddForm(int $courseId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $course = $this->courseRepo->findById($courseId);
        render('courses/members/new', [
            'title' => 'Add Member',
            'course' => $course,
            'users' => $this->userRepo->all(),
            'roles' => $this->membershipRepo->allowedRoles(),
            'error' => null,
        ]);
    }

    public function addMemberHtml(int $courseId): void
    {
        verify_csrf(false);
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->addMember($courseId, request_data());
        if (!$result['success']) {
            $course = $this->courseRepo->findById($courseId);
            render('courses/members/new', [
                'title' => 'Add Member',
                'course' => $course,
                'users' => $this->userRepo->all(),
                'roles' => $this->membershipRepo->allowedRoles(),
                'error' => $result['error'],
            ]);
            return;
        }

        redirect_to('/courses/' . $courseId . '/members');
    }

    public function addMemberApi(int $courseId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->addMember($courseId, request_data());
        if (!$result['success']) {
            $status = $result['error'] === 'Course not found' || $result['error'] === 'User not found' ? 404 : 422;
            json_response($result, $status);
        }

        json_response($result, 201);
    }

    private function addMember(int $courseId, array $data): array
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $role = trim((string) ($data['role_in_course'] ?? ''));
        if ($userId <= 0 || $role === '') {
            return ['success' => false, 'error' => 'user_id and role_in_course are required'];
        }
        if (!$this->membershipRepo->isValidRole($role)) {
            return ['success' => false, 'error' => 'Invalid role_in_course'];
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if ($this->membershipRepo->findByCourseAndUser($courseId, $userId)) {
            return ['success' => false, 'error' => 'User is already a member of this course'];
        }

        $membershipId = $this->membershipRepo->create([
            'course_id' => $courseId,
            'user_id' => $userId,
            'role_in_course' => $role,
        ]);

        return ['success' => true, 'membership' => $this->membershipRepo->findById($membershipId)];
    }

    public function listMembersHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $course = $this->courseRepo->findById($courseId);
        render('courses/members/index', [
            'title' => 'Course Members',
            'course' => $course,
            'members' => $this->membershipRepo->listByCourse($courseId),
            'roles' => $this->membershipRepo->allowedRoles(),
            'can_manage' => current_user_can_manage_course($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
        ]);
    }

    public function listMembersApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $course = $this->courseRepo->findById($courseId);
        json_response([
            'success' => true,
            'course' => $course,
            'members' => $this->membershipRepo->listByCourse($courseId),
        ]);
    }

    public function updateMemberRoleHtml(int $courseId, int $membershipId): void
    {
        verify_csrf(false);
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->updateMemberRole($courseId, $membershipId, request_data());
        if (!$result['success']) {
            redirect_to('/courses/' . $courseId . '/members');
        }
        redirect_to('/courses/' . $courseId . '/members');
    }

    public function updateMemberRoleApi(int $courseId, int $membershipId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->updateMemberRole($courseId, $membershipId, request_data());
        if (!$result['success']) {
            $status = $result['error'] === 'Membership not found' || $result['error'] === 'Course not found' ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function updateMemberRole(int $courseId, int $membershipId, array $data): array
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }

        $membership = $this->membershipRepo->findById($membershipId);
        if (!$membership || (int) $membership['course_id'] !== $courseId) {
            return ['success' => false, 'error' => 'Membership not found'];
        }

        $role = trim((string) ($data['role_in_course'] ?? ''));
        if ($role === '') {
            return ['success' => false, 'error' => 'role_in_course is required'];
        }
        if (!$this->membershipRepo->isValidRole($role)) {
            return ['success' => false, 'error' => 'Invalid role_in_course'];
        }

        $this->membershipRepo->updateRole($membershipId, $role);
        return ['success' => true, 'membership' => $this->membershipRepo->findById($membershipId)];
    }

    public function removeMemberHtml(int $courseId, int $membershipId): void
    {
        verify_csrf(false);
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->removeMember($courseId, $membershipId);
        redirect_to('/courses/' . $courseId . '/members');
    }

    public function removeMemberApi(int $courseId, int $membershipId): void
    {
        require_teacher_or_admin($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->removeMember($courseId, $membershipId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function removeMember(int $courseId, int $membershipId): array
    {
        $course = $this->courseRepo->findById($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }

        $membership = $this->membershipRepo->findById($membershipId);
        if (!$membership || (int) $membership['course_id'] !== $courseId) {
            return ['success' => false, 'error' => 'Membership not found'];
        }

        $this->membershipRepo->delete($membershipId);
        return ['success' => true];
    }

    public function myMembershipsHtml(): void
    {
        $userId = require_login($this->config, false);
        render('memberships/index', [
            'title' => 'My Memberships',
            'memberships' => $this->membershipRepo->listByUser($userId),
        ]);
    }

    public function myMembershipsApi(): void
    {
        $userId = require_login($this->config, true);
        json_response([
            'success' => true,
            'memberships' => $this->membershipRepo->listByUser($userId),
        ]);
    }
}