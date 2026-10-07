<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class CommentController
{
    public function __construct(
        private CommentRepository $commentRepo,
        private PostRepository $postRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function createHtml(int $courseId, int $postId): void
    {
        $userId = require_login($this->config, false);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->create($courseId, $postId, $userId, request_data());
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function createApi(int $courseId, int $postId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->create($courseId, $postId, $userId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Post not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 201);
    }

    private function create(int $courseId, int $postId, int $userId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return ['success' => false, 'error' => 'body is required'];
        }
        $commentId = $this->commentRepo->create(['post_id' => $postId, 'course_id' => $courseId, 'author_id' => $userId, 'body' => $body]);
        return ['success' => true, 'comment' => $this->commentRepo->findById($courseId, $postId, $commentId)];
    }

    public function listHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            http_response_code(404);
        }
        render('courses/posts/comments', ['title' => 'Comments', 'post' => $post, 'course' => $this->courseRepo->findById($courseId), 'comments' => $post ? $this->commentRepo->listByPost($courseId, $postId) : []]);
    }

    public function listApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        if (!$this->postRepo->findById($courseId, $postId)) {
            json_response(['success' => false, 'error' => 'Post not found'], 404);
        }
        json_response(['success' => true, 'comments' => $this->commentRepo->listByPost($courseId, $postId)]);
    }

    public function updateHtml(int $courseId, int $postId, int $commentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->update($courseId, $postId, $commentId, request_data());
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function updateApi(int $courseId, int $postId, int $commentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $postId, $commentId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Post not found', 'Comment not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $postId, int $commentId, array $data): array
    {
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        if (!$this->commentRepo->findById($courseId, $postId, $commentId)) {
            return ['success' => false, 'error' => 'Comment not found'];
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return ['success' => false, 'error' => 'body is required'];
        }
        $this->commentRepo->update($courseId, $postId, $commentId, $body);
        return ['success' => true, 'comment' => $this->commentRepo->findById($courseId, $postId, $commentId)];
    }

    public function deleteHtml(int $courseId, int $postId, int $commentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId, $postId, $commentId);
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function deleteApi(int $courseId, int $postId, int $commentId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId, $postId, $commentId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $postId, int $commentId): array
    {
        if (!$this->commentRepo->findById($courseId, $postId, $commentId)) {
            return ['success' => false, 'error' => 'Comment not found'];
        }
        $this->commentRepo->delete($courseId, $postId, $commentId);
        return ['success' => true];
    }
}
