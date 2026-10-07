<?php
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';
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
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) { http_response_code(404); echo 'Not found'; return; }
        if ((int)$comment['author_id'] !== (int)$u['user_id']) {
            $mrepo = new MembershipRepo($this->pdo);
            $membership = $mrepo->getByCourseAndUser($courseId, (int)$u['user_id']);
            if (!$membership || !in_array($membership['role_in_course'], ['teacher', 'admin'])) {
                http_response_code(403); render('error/403', ['title' => 'Forbidden']); return;
            }
        }
        $body = trim($_POST['body'] ?? '');
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
        if ((int)$comment['author_id'] !== (int)$u['user_id']) {
            $mrepo = new MembershipRepo($this->pdo);
            $membership = $mrepo->getByCourseAndUser($courseId, (int)$u['user_id']);
            if (!$membership || !in_array($membership['role_in_course'], ['teacher', 'admin'])) {
                http_response_code(403); render('error/403', ['title' => 'Forbidden']); return;
            }
        }
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
        $repo = new CommentRepo($this->pdo);
        $comment = $repo->getById($courseId, $postId, $commentId);
        if (!$comment) json_response(['error' => 'not found'], 404);
        if ((int)$comment['author_id'] !== (int)$u['user_id']) {
            $mrepo = new MembershipRepo($this->pdo);
            $membership = $mrepo->getByCourseAndUser($courseId, (int)$u['user_id']);
            if (!$membership || !in_array($membership['role_in_course'], ['teacher', 'admin'])) {
                json_response(['error' => 'Forbidden: not comment author or staff'], 403);
            }
        }
        $body = trim($data['body'] ?? '');
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
        if ((int)$comment['author_id'] !== (int)$u['user_id']) {
            $mrepo = new MembershipRepo($this->pdo);
            $membership = $mrepo->getByCourseAndUser($courseId, (int)$u['user_id']);
            if (!$membership || !in_array($membership['role_in_course'], ['teacher', 'admin'])) {
                json_response(['error' => 'Forbidden: not comment author or staff'], 403);
            }
        }
        $repo->delete($courseId, $postId, $commentId);
        json_response(['ok' => true]);
    }
}
