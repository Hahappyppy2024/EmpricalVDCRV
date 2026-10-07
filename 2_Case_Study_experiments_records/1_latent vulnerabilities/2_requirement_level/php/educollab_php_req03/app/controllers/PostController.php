<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class PostController
{
    public function __construct(
        private PostRepository $postRepo,
        private CommentRepository $commentRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function showCreateForm(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/posts/new', ['title' => 'New Post', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function createHtml(int $courseId): void
    {
        $userId = require_login($this->config, false);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            render('courses/posts/new', ['title' => 'New Post', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/posts/' . $result['post']['post_id']);
    }

    public function createApi(int $courseId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            json_response($result, $result['error'] === 'Course not found' ? 404 : 422);
        }
        json_response($result, 201);
    }

    private function create(int $courseId, int $userId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['success' => false, 'error' => 'title and body are required'];
        }
        $postId = $this->postRepo->create(['course_id' => $courseId, 'author_id' => $userId, 'title' => $title, 'body' => $body]);
        return ['success' => true, 'post' => $this->postRepo->findById($courseId, $postId)];
    }

    public function listHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/posts/index', ['title' => 'Posts', 'course' => $this->courseRepo->findById($courseId), 'posts' => $this->postRepo->listByCourse($courseId)]);
    }

    public function listApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'posts' => $this->postRepo->listByCourse($courseId)]);
    }

    public function getHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $course = $this->courseRepo->findById($courseId);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            http_response_code(404);
        }
        render('courses/posts/show', ['title' => $post ? $post['title'] : 'Post Not Found', 'course' => $course, 'post' => $post, 'comments' => $post ? $this->commentRepo->listByPost($courseId, $postId) : [], 'comment_error' => null]);
    }

    public function getApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            json_response(['success' => false, 'error' => 'Post not found'], 404);
        }
        json_response(['success' => true, 'post' => $post, 'comments' => $this->commentRepo->listByPost($courseId, $postId)]);
    }

    public function showEditForm(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            http_response_code(404);
            render('courses/posts/show', ['title' => 'Post Not Found', 'course' => $this->courseRepo->findById($courseId), 'post' => null, 'comments' => [], 'comment_error' => null]);
            return;
        }
        render('courses/posts/edit', ['title' => 'Edit Post', 'course' => $this->courseRepo->findById($courseId), 'post' => $post, 'error' => null]);
    }

    public function updateHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->update($courseId, $postId, request_data());
        if (!$result['success']) {
            render('courses/posts/edit', ['title' => 'Edit Post', 'course' => $this->courseRepo->findById($courseId), 'post' => $this->postRepo->findById($courseId, $postId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function updateApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $postId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Post not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $postId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['success' => false, 'error' => 'title and body are required'];
        }
        $this->postRepo->update($courseId, $postId, ['title' => $title, 'body' => $body]);
        return ['success' => true, 'post' => $this->postRepo->findById($courseId, $postId)];
    }

    public function deleteHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId, $postId);
        redirect_to('/courses/' . $courseId . '/posts');
    }

    public function deleteApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId, $postId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $postId): array
    {
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        $this->postRepo->delete($courseId, $postId);
        return ['success' => true];
    }
}
