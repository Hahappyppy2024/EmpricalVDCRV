<?php
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class CommentController {
    public function __construct(private PDO $pdo) {}

    private function denyCommentMutation(bool $api): void {
        if ($api) {
            json_response(['error' => 'forbidden'], 403);
        }
        http_response_code(403);
        render('403', ['title' => 'Forbidden']);
        exit;
    }

    private function requireManageCommentPermission(int $courseId, array $user, array $comment, bool $api): void {
        if ((int)$comment['author_id'] === (int)$user['user_id']) {
            return;
        }

        if (is_global_admin($user)) {
            return;
        }

        $membershipRepo = new MembershipRepo($this->pdo);
        $membership = null;
        if (method_exists($membershipRepo, 'getByCourseAndUser')) {
            $membership = $membershipRepo->getByCourseAndUser($courseId, (int)$user['user_id']);
        }

        $role = (string)($membership['role_in_course'] ?? '');
        $allowed = ['teacher', 'assistant', 'senior-assistant', 'admin'];
        if (!in_array($role, $allowed, true)) {
            $this->denyCommentMutation($api);
        }
    }

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
        require_course_member($this->pdo, $courseId, $u, false);
        $body = trim($_POST['body'] ?? '');
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) { http_response_code(404); echo 'Not found'; return; }
        $this->requireManageCommentPermission($courseId, $u, $comment, false);
        $repo->update($courseId, $postId, $commentId, $body);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) { http_response_code(404); echo 'Not found'; return; }
        $this->requireManageCommentPermission($courseId, $u, $comment, false);
        $repo->delete($courseId, $postId, $commentId);
        redirect("/courses/$courseId/posts/$postId");
    }

    // API
    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new CommentRepo($this->pdo);
        json_response(['comments' => $repo->listByPost($courseId, $postId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $body = trim($data['body'] ?? '');
        $repo = new CommentRepo($this->pdo);
        $c = $repo->create($courseId, $postId, (int)$u['user_id'], $body);
        json_response(['comment' => $c]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $body = trim($data['body'] ?? '');
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) json_response(['error' => 'not found'], 404);
        $this->requireManageCommentPermission($courseId, $u, $comment, true);
        $c = $repo->update($courseId, $postId, $commentId, $body);
        json_response(['comment' => $c]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $commentId = (int)$p['comment_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) json_response(['error' => 'not found'], 404);
        $this->requireManageCommentPermission($courseId, $u, $comment, true);
        $repo->delete($courseId, $postId, $commentId);
        json_response(['ok' => true]);
    }
}