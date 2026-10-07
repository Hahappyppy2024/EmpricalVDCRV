<?php

require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/repos/CourseRepo.php';

function require_course_member(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    $mrepo = new MembershipRepo($pdo);
    if (is_global_admin($user)) {
        return ['role_in_course' => 'admin', 'user_id' => $user['user_id'], 'course_id' => $courseId];
    }
    $m = $mrepo->getByCourseAndUser($courseId, (int)$user['user_id']);
    if (!$m) {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

function role_is_staff(string $role): bool {
    return in_array($role, ['admin','teacher','assistant','senior-assistant'], true);
}

function require_course_staff(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    $m = require_course_member($pdo, $courseId, $user, $api);
    $role = $m['role_in_course'] ?? '';
    if (!role_is_staff($role)) {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

function require_course_teacher_or_admin(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    $m = require_course_member($pdo, $courseId, $user, $api);
    $role = $m['role_in_course'] ?? '';
    if (!in_array($role, ['admin','teacher'], true)) {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

function require_course_student(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    $m = require_course_member($pdo, $courseId, $user, $api);
    $role = $m['role_in_course'] ?? '';
    if ($role !== 'student') {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

