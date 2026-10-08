<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Role / capability check service. Every privileged route verifies a
 * capability before executing, mirroring the real-world role and capability
 * anchor of the alignment target. Course-scoped capabilities are additionally
 * checked against the course owner.
 */
final class CapabilityService
{
    /** @var array<string, list<string>> */
    private array $capabilities = [
        'visitor' => [],
        'student' => [
            'course.discover',
            'enrollment.self',
            'material.download',
            'discussion.read',
            'discussion.post',
            'discussion.edit.own',
            'assignment.read',
            'assignment.submit',
            'quiz.read',
            'quiz.take',
            'grade.view.own',
            'frontend.integration',
        ],
        'instructor' => [
            'course.discover',
            'course.create',
            'course.edit.own',
            'enrollment.roster',
            'material.upload.own',
            'material.download',
            'announcement.publish.own',
            'discussion.read',
            'discussion.post',
            'discussion.edit.any',
            'assignment.manage.own',
            'assignment.grade.own',
            'quiz.manage.own',
            'quiz.review.own',
            'grade.manage.own',
            'grade.export.own',
            'frontend.integration',
        ],
        'admin' => [
            'course.discover',
            'course.create',
            'course.edit.any',
            'enrollment.manage',
            'material.upload.any',
            'material.download',
            'announcement.publish.any',
            'discussion.read',
            'discussion.post',
            'discussion.edit.any',
            'assignment.manage.any',
            'assignment.grade.any',
            'quiz.manage.any',
            'quiz.review.any',
            'grade.manage.any',
            'grade.export.any',
            'report.bulk',
            'admin.api',
            'settings.manage',
            'audit.view',
            'frontend.integration',
        ],
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function has(?array $user, string $capability): bool
    {
        if ($user === null) {
            return false;
        }
        $role = $user['role_name'] ?? 'visitor';
        return in_array($capability, $this->capabilities[$role] ?? [], true);
    }

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed>|null $course
     */
    public function owns(?array $user, ?array $course): bool
    {
        if ($user === null) {
            return false;
        }
        $isAdmin = ($user['role_name'] ?? '') === 'admin';
        $isOwner = $course !== null && (int) $course['instructor_id'] === (int) $user['id'];
        return $isAdmin || $isOwner;
    }

    public function isAdmin(?array $user): bool
    {
        return $user !== null && ($user['role_name'] ?? '') === 'admin';
    }

    public function isInstructor(?array $user): bool
    {
        return $user !== null && ($user['role_name'] ?? '') === 'instructor';
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function isStudent(?array $user): bool
    {
        return $user !== null && ($user['role_name'] ?? '') === 'student';
    }
}
