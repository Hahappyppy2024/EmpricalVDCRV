<?php
require_once APP_BASE . '/src/repos/PostRepo.php';
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class PostController {
    public function __construct(private PDO $pdo) {}

    private function denyPostMutation(bool $api): void {
        if ($api) {
            json_response(['error' => 'forbidden'], 403);
        }
        http_response_code(403);
        render('403', ['title' => 'Forbidden']);
        exit;
    }

    private function requireManagePostPermission(int $courseId, array $user, array $post, bool $api): void {
        if ((int)$post['author_id'] === (int)$user['user_id']) {
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
            $this->denyPostMutation($api);
        }
    }

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        render('posts/list', ['title' => 'Posts', 'course_id' => $courseId, 'posts' => $repo->listByCourse($courseId)]);
    }

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
        $crepo = new CommentRepo($this->pdo);
        $comments = $crepo->listByPost($courseId, $postId);
        render('posts/detail', ['title' => $post['title'], 'course_id' => $courseId, 'post' => $post, 'comments' => $comments]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        $this->requireManagePostPermission($courseId, $u, $post, false);
        render('posts/edit', ['title' => 'Edit Post', 'course_id' => $courseId, 'post' => $post]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        $this->requireManagePostPermission($courseId, $u, $post, false);
        $repo->update($courseId, $postId, $title, $body);
        redirect("/courses/$courseId/posts/$postId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) { http_response_code(404); echo 'Not found'; return; }
        $this->requireManagePostPermission($courseId, $u, $post, false);
        $repo->delete($courseId, $postId);
        redirect("/courses/$courseId/posts");
    }

    // API
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
        json_response(['post' => $post]);
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
        require_course_member($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) json_response(['error' => 'not found'], 404);
        $this->requireManagePostPermission($courseId, $u, $post, true);
        $post = $repo->update($courseId, $postId, $title, $body);
        json_response(['post' => $post]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $postId = (int)$p['post_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new PostRepo($this->pdo);
        $post = $repo->getById($courseId, $postId);
        if (!$post) json_response(['error' => 'not found'], 404);
        $this->requireManagePostPermission($courseId, $u, $post, true);
        $repo->delete($courseId, $postId);
        json_response(['ok' => true]);
    }
}