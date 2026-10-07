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

    public function listHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function listApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        if (!$this->postRepo->findById($courseId, $postId)) {
            json_response(['success' => false, 'error' => 'Post not found'], 404);
        }
        json_response(['success' => true, 'comments' => $this->commentRepo->listByPost($courseId, $postId)]);
    }

    public function createHtml(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $result = $this->create($courseId, $postId, $userId, request_data());

        if (!$result['success']) {
            $post = $this->postRepo->findById($courseId, $postId);
            $membership = current_course_membership($this->config, $this->membershipRepo, $this->courseRepo, $courseId);
            $comments = $post ? $this->commentRepo->listByPost($courseId, $postId) : [];

            foreach ($comments as &$comment) {
                $comment['can_manage'] = $membership
                    ? can_manage_course_content($membership, $userId, (int) $comment['author_id'])
                    : false;
            }
            unset($comment);

            render('courses/posts/show', [
                'title' => $post ? $post['title'] : 'Post Not Found',
                'course' => $this->courseRepo->findById($courseId),
                'post' => $post,
                'comments' => $comments,
                'comment_error' => $result['error'],
                'can_manage_post' => $post && $membership
                    ? can_manage_course_content($membership, $userId, (int) $post['author_id'])
                    : false,
            ]);
            return;
        }

        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function createApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->create($courseId, $postId, $userId, request_data());

        if (!$result['success']) {
            $status = $result['error'] === 'Post not found' ? 404 : 422;
            json_response($result, $status);
        }

        json_response($result, 201);
    }

    private function create(int $courseId, int $postId, int $userId, array $data): array
    {
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return ['success' => false, 'error' => 'body is required'];
        }

        $commentId = $this->commentRepo->create([
            'course_id' => $courseId,
            'post_id' => $postId,
            'author_id' => $userId,
            'body' => $body,
        ]);

        return ['success' => true, 'comment' => $this->commentRepo->findById($courseId, $postId, $commentId)];
    }

    public function updateHtml(int $courseId, int $postId, int $commentId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $this->update($courseId, $postId, $commentId, $userId, $membership, request_data());
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function updateApi(int $courseId, int $postId, int $commentId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->update($courseId, $postId, $commentId, $userId, $membership, request_data());

        if (!$result['success']) {
            $status = in_array($result['error'], ['Post not found', 'Comment not found'], true)
                ? 404
                : ($result['error'] === 'Forbidden' ? 403 : 422);
            json_response($result, $status);
        }

        json_response($result, 200);
    }

    private function update(int $courseId, int $postId, int $commentId, int $userId, array $membership, array $data): array
    {
        if (!$this->postRepo->findById($courseId, $postId)) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        $comment = $this->commentRepo->findById($courseId, $postId, $commentId);
        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }

        if (!can_manage_course_content($membership, $userId, (int) $comment['author_id'])) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return ['success' => false, 'error' => 'body is required'];
        }

        if (can_moderate_course_content($membership)) {
            $this->commentRepo->update($courseId, $postId, $commentId, $body);
        } else {
            $this->commentRepo->updateOwned($courseId, $postId, $commentId, $userId, $body);
        }

        return ['success' => true, 'comment' => $this->commentRepo->findById($courseId, $postId, $commentId)];
    }

    public function deleteHtml(int $courseId, int $postId, int $commentId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $this->delete($courseId, $postId, $commentId, $userId, $membership);
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function deleteApi(int $courseId, int $postId, int $commentId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->delete($courseId, $postId, $commentId, $userId, $membership);

        if (!$result['success']) {
            json_response($result, $result['error'] === 'Forbidden' ? 403 : 404);
        }

        json_response($result, 200);
    }

    private function delete(int $courseId, int $postId, int $commentId, int $userId, array $membership): array
    {
        $comment = $this->commentRepo->findById($courseId, $postId, $commentId);
        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }

        if (!can_manage_course_content($membership, $userId, (int) $comment['author_id'])) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        if (can_moderate_course_content($membership)) {
            $this->commentRepo->delete($courseId, $postId, $commentId);
        } else {
            $this->commentRepo->deleteOwned($courseId, $postId, $commentId, $userId);
        }

        return ['success' => true];
    }
}