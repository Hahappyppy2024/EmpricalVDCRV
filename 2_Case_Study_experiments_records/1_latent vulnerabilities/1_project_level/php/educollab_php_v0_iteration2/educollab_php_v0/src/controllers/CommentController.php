<?php
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class CommentController {
    public function __construct(private PDO $pdo) {}

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $body = trim($_POST['body'] ?? '');
        (new CommentRepo($this->pdo))->create($courseId, $postId, (int)$u['user_id'], $body);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, false);
        require_csrf();
        $body = trim($_POST['body'] ?? '');
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) { http_response_code(404); echo 'Not found'; return; }
        if (!can_manage_comment($membership, $comment, $u)) { http_response_code(403); echo 'Forbidden'; return; }
        $repo->update($courseId, $postId, $commentId, $body, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, false);
        require_csrf();
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) { http_response_code(404); echo 'Not found'; return; }
        if (!can_manage_comment($membership, $comment, $u)) { http_response_code(403); echo 'Forbidden'; return; }
        $repo->delete($courseId, $postId, $commentId, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        json_response(['comments' => (new CommentRepo($this->pdo))->listByPost($courseId, $postId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $body = trim($data['body'] ?? '');
        $comment = (new CommentRepo($this->pdo))->create($courseId, $postId, (int)$u['user_id'], $body);
        json_response(['comment' => $comment]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $body = trim($data['body'] ?? '');
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) json_response(['error' => 'not found'], 404);
        if (!can_manage_comment($membership, $comment, $u)) json_response(['error' => 'forbidden'], 403);
        $comment = $repo->update($courseId, $postId, $commentId, $body, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        json_response(['comment' => $comment]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, true);
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) json_response(['error' => 'not found'], 404);
        if (!can_manage_comment($membership, $comment, $u)) json_response(['error' => 'forbidden'], 403);
        $repo->delete($courseId, $postId, $commentId, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        json_response(['ok' => true]);
    }
}