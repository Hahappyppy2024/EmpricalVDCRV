<?php

require_once APP_BASE . '/src/controllers/_guards.php';
require_once APP_BASE . '/src/repos/InviteTokenRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';

class InviteController {
    public function __construct(private PDO $pdo) {}

    // ---------------- API ----------------

    /**
     * POST /api/courses/{course_id}/invites
     * Body: {role_in_course?: 'student', ttl_minutes?: int}
     */
    public function apiCreate(array $p): void {
        $user = require_api_login();
        $courseId = (int)($p['course_id'] ?? 0);
        require_course_staff($this->pdo, $courseId, $user, true);

        $data = parse_json_body();
        $role = (string)($data['role_in_course'] ?? 'student');
        // Keep minimal: only allow inviting students
        if ($role !== 'student') {
            json_response(['error' => 'invalid_role'], 400);
        }
        $ttl = (int)($data['ttl_minutes'] ?? 1440);

        $repo = new InviteTokenRepo($this->pdo);
        $created = $repo->create($courseId, $role, $ttl, (int)$user['user_id']);
        $invite = $created['invite'];
        $token = $created['token'];

        json_response([
            'invite' => $invite,
            'invite_link' => '/join?token=' . $token,
            'expires_at' => $invite['expires_at'] ?? null,
            'role_in_course' => $role,
        ], 200);
    }

    /**
     * POST /api/join  Body: {token: string}
     * GET  /api/join?token=...
     */
    public function apiJoin(): void {
        $user = require_api_login();
        $token = '';
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($m) === 'POST') {
            $data = parse_json_body();
            $token = (string)($data['token'] ?? '');
        } else {
            $token = (string)($_GET['token'] ?? '');
        }

        $repo = new InviteTokenRepo($this->pdo);
        $inv = $repo->findValidByToken($token);
        if (!$inv) {
            json_response(['error' => 'invalid_or_expired'], 400);
        }

        $courseId = (int)$inv['course_id'];
        $role = (string)$inv['role_in_course'];

        $mrepo = new MembershipRepo($this->pdo);
        $membership = $mrepo->add($courseId, (int)$user['user_id'], $role);

        $repo->markUsed((int)$inv['invite_id'], (int)$user['user_id']);

        json_response([
            'joined' => true,
            'course_id' => $courseId,
            'membership' => $membership,
        ], 200);
    }

    // ---------------- Web ----------------

    /**
     * GET /join?token=...
     */
    public function joinPage(): void {
        $user = require_login();
        $token = (string)($_GET['token'] ?? '');
        $repo = new InviteTokenRepo($this->pdo);
        $inv = $repo->findValidByToken($token);
        if (!$inv) {
            http_response_code(400);
            echo 'Invalid or expired invite.';
            exit;
        }

        $courseId = (int)$inv['course_id'];
        $role = (string)$inv['role_in_course'];

        $mrepo = new MembershipRepo($this->pdo);
        $mrepo->add($courseId, (int)$user['user_id'], $role);
        $repo->markUsed((int)$inv['invite_id'], (int)$user['user_id']);
        redirect('/courses/' . $courseId);
    }
}
