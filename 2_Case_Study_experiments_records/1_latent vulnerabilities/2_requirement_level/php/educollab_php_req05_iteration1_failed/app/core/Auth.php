<?php

declare(strict_types=1);

require_once __DIR__ . '/View.php';

function current_user_id(array $config): ?int
{
    $key = $config['session_user_key'];
    return isset($_SESSION[$key]) ? (int) $_SESSION[$key] : null;
}

function require_login(array $config, bool $api = false): int
{
    $userId = current_user_id($config);
    if ($userId !== null) {
        return $userId;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Authentication required'], 401);
    }

    redirect_to('/login');
}

function login_user(array $config, int $userId): void
{
    $_SESSION[$config['session_user_key']] = $userId;
}

function logout_user(array $config): void
{
    unset($_SESSION[$config['session_user_key']]);
}

function ensure_course_creator_membership(MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId, int $userId): void
{
    $course = $courseRepo->findById($courseId);
    if ($course && (int) $course['created_by'] === $userId) {
        $membershipRepo->ensureMembership($courseId, $userId, 'teacher');
    }
}

function current_course_membership(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId): ?array
{
    $userId = current_user_id($config);
    if ($userId === null) {
        return null;
    }

    ensure_course_creator_membership($membershipRepo, $courseRepo, $courseId, $userId);
    return $membershipRepo->findByCourseAndUser($courseId, $userId);
}

function require_course_member(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId, bool $api = false): array
{
    require_login($config, $api);
    $course = $courseRepo->findById($courseId);
    if (!$course) {
        if ($api) {
            json_response(['success' => false, 'error' => 'Course not found'], 404);
        }
        http_response_code(404);
        render('courses/show', ['title' => 'Course Not Found', 'course' => null]);
        exit;
    }

    $membership = current_course_membership($config, $membershipRepo, $courseRepo, $courseId);
    if ($membership) {
        return $membership;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Course membership required'], 403);
    }

    http_response_code(403);
    render('home', ['title' => 'Forbidden', 'message' => 'Course membership required']);
    exit;
}

function require_teacher_or_admin(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId, bool $api = false): array
{
    $membership = require_course_member($config, $membershipRepo, $courseRepo, $courseId, $api);
    if (in_array($membership['role_in_course'], ['teacher', 'admin'], true)) {
        return $membership;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Teacher or admin role required'], 403);
    }

    http_response_code(403);
    render('home', ['title' => 'Forbidden', 'message' => 'Teacher or admin role required']);
    exit;
}

function require_course_staff(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId, bool $api = false): array
{
    $membership = require_course_member($config, $membershipRepo, $courseRepo, $courseId, $api);
    if (in_array($membership['role_in_course'], ['teacher', 'admin', 'assistant', 'senior-assistant'], true)) {
        return $membership;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Course staff role required'], 403);
    }

    http_response_code(403);
    render('home', ['title' => 'Forbidden', 'message' => 'Course staff role required']);
    exit;
}

function current_user_can_manage_course(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId): bool
{
    $membership = current_course_membership($config, $membershipRepo, $courseRepo, $courseId);
    return $membership !== null && in_array($membership['role_in_course'], ['teacher', 'admin'], true);
}


function current_course_role(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId): ?string
{
    $membership = current_course_membership($config, $membershipRepo, $courseRepo, $courseId);
    return $membership['role_in_course'] ?? null;
}

function require_student(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId, bool $api = false): int
{
    $membership = require_course_member($config, $membershipRepo, $courseRepo, $courseId, $api);
    if (($membership['role_in_course'] ?? null) === 'student') {
        return (int) $membership['user_id'];
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Student role required'], 403);
    }

    http_response_code(403);
    render('home', ['title' => 'Forbidden', 'message' => 'Student role required']);
    exit;
}

function current_user_is_course_staff(array $config, MembershipRepository $membershipRepo, CourseRepository $courseRepo, int $courseId): bool
{
    $membership = current_course_membership($config, $membershipRepo, $courseRepo, $courseId);
    return $membership !== null && in_array($membership['role_in_course'], ['teacher', 'admin', 'assistant', 'senior-assistant'], true);
}
