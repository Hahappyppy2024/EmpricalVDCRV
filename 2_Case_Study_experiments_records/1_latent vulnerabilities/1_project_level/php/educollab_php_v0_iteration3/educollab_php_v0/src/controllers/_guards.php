<?php
require_once APP_BASE . '/src/repos/MembershipRepo.php';

function require_course_member(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    if (is_global_admin($user)) {
        return [
            'role_in_course' => 'teacher',
            'course_id' => $courseId,
            'user_id' => (int)$user['user_id']
        ];
    }

    $repo = new MembershipRepo($pdo);
    $m = $repo->getByCourseAndUser($courseId, (int)$user['user_id']);
    if (!$m) {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

function require_course_staff(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    $m = require_course_member($pdo, $courseId, $user, $api);
    if (!role_is_staff((string)($m['role_in_course'] ?? '')) && !is_global_admin($user)) {
        if ($api) json_response(['error' => 'forbidden'], 403);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $m;
}

function require_course_teacher_or_admin(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    return require_course_staff($pdo, $courseId, $user, $api);
}

function require_course_student(PDO $pdo, int $courseId, array $user, bool $api=false): array {
    return require_course_member($pdo, $courseId, $user, $api);
}

function can_manage_post(array $membership, array $post, array $user): bool {
    if (is_global_admin($user)) return true;
    if (role_is_staff((string)($membership['role_in_course'] ?? ''))) return true;
    return (int)($post['author_id'] ?? 0) === (int)($user['user_id'] ?? 0);
}

function can_manage_comment(array $membership, array $comment, array $user): bool {
    if (is_global_admin($user)) return true;
    if (role_is_staff((string)($membership['role_in_course'] ?? ''))) return true;
    return (int)($comment['author_id'] ?? 0) === (int)($user['user_id'] ?? 0);
}