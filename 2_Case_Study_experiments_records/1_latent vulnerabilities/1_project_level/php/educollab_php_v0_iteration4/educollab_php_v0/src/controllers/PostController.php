<?php
require_once APP_BASE . '/src/repos/PostRepo.php';
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class PostController {
    public function __construct(private PDO $pdo) {}

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        render('posts/new', ['title' => 'New Post', 'course_id' => $courseId]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->create($courseId, (int)$u['user_id'], $title, $body);
        redirect("/courses/$courseId/posts/{$post['post_id']}");
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        $comments = (new CommentRepo($this->pdo))->listByPost($courseId, $postId);
        render('posts/detail', [
            'title' => $post['title'],
            'course_id' => $courseId,
            'post' => $post,
            'comments' => $comments,
        ]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        if (!can_manage_post($membership, $post, $u)) { http_response_code(403); echo 'Forbidden'; return; }
        render('posts/edit', ['title' => 'Edit Post', 'course_id' => $courseId, 'post' => $post]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        if (!can_manage_post($membership, $post, $u)) { http_response_code(403); echo 'Forbidden'; return; }
        $repo->update($courseId, $postId, $title, $body, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        if (!can_manage_post($membership, $post, $u)) { http_response_code(403); echo 'Forbidden'; return; }
        $repo->delete($courseId, $postId, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        redirect("/courses/$courseId/posts");
    }

    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new PostRepo($this->pdo);
        json_response(['posts' => $repo->listByCourse($courseId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->create($courseId, (int)$u['user_id'], $title, $body);
        json_response(['post' => $post], 201);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) json_response(['error' => 'not found'], 404);
        json_response(['post' => $post]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) json_response(['error' => 'not found'], 404);
        if (!can_manage_post($membership, $post, $u)) json_response(['error' => 'forbidden'], 403);
        $post = $repo->update($courseId, $postId, $title, $body, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        json_response(['post' => $post]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        $membership = require_course_member($this->pdo, $courseId, $u, true);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) json_response(['error' => 'not found'], 404);
        if (!can_manage_post($membership, $post, $u)) json_response(['error' => 'forbidden'], 403);
        $repo->delete($courseId, $postId, (role_is_staff((string)($membership['role_in_course'] ?? '')) || is_global_admin($u)) ? null : (int)$u['user_id']);
        json_response(['ok' => true]);
    }
}