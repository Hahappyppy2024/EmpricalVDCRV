<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../repositories/PostRepository.php';
require_once __DIR__ . '/../repositories/CommentRepository.php';
require_once __DIR__ . '/../repositories/CourseRepository.php';
require_once __DIR__ . '/../repositories/MembershipRepository.php';

class PostController extends Controller
{
    private PostRepository $postRepo;
    private CommentRepository $commentRepo;
    private CourseRepository $courseRepo;
    private MembershipRepository $membershipRepo;

    public function __construct(array $config, PDO $pdo)
    {
        parent::__construct($config, $pdo);
        $this->postRepo = new PostRepository($pdo);
        $this->commentRepo = new CommentRepository($pdo);
        $this->courseRepo = new CourseRepository($pdo);
        $this->membershipRepo = new MembershipRepository($pdo);
    }

    public function listHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $course = $this->courseRepo->findById($courseId);
        $posts = $this->postRepo->listByCourse($courseId);
        render('courses/posts/index', ['title' => 'Posts', 'course' => $course, 'posts' => $posts]);
    }

    public function listApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'posts' => $this->postRepo->listByCourse($courseId)]);
    }

    public function createHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/posts/create', ['title' => 'New Post', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function storeHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            render('courses/posts/create', ['title' => 'New Post', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/posts/' . $result['post']['post_id']);
    }

    public function storeApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            json_response($result, 422);
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
        $post = $this->postRepo->create($courseId, $userId, $title, $body);
        return ['success' => true, 'post' => $post];
    }

    public function getHtml(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $course = $this->courseRepo->findById($courseId);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            http_response_code(404);
        }
        $comments = $post ? $this->commentRepo->listByPost($courseId, $postId) : [];
        foreach ($comments as &$comment) {
            $comment['can_manage'] = can_manage_course_content($membership, $userId, (int) $comment['author_id']);
        }
        unset($comment);
        render('courses/posts/show', [
            'title' => $post ? $post['title'] : 'Post Not Found',
            'course' => $course,
            'post' => $post,
            'comments' => $comments,
            'comment_error' => null,
            'can_manage_post' => $post ? can_manage_course_content($membership, $userId, (int) $post['author_id']) : false,
        ]);
    }

    public function getApi(int $courseId, int $postId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            json_response(['success' => false, 'error' => 'Post not found'], 404);
        }
        json_response(['success' => true, 'post' => $post]);
    }

    public function showEditForm(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            http_response_code(404);
            render('courses/posts/show', ['title' => 'Post Not Found', 'course' => $this->courseRepo->findById($courseId), 'post' => null, 'comments' => [], 'comment_error' => null, 'can_manage_post' => false]);
            return;
        }
        if (!can_manage_course_content($membership, $userId, (int) $post['author_id'])) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        render('courses/posts/edit', ['title' => 'Edit Post', 'course' => $this->courseRepo->findById($courseId), 'post' => $post, 'error' => null]);
    }

    public function updateHtml(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $result = $this->update($courseId, $postId, $userId, $membership, request_data());
        if (!$result['success']) {
            render('courses/posts/edit', ['title' => 'Edit Post', 'course' => $this->courseRepo->findById($courseId), 'post' => $this->postRepo->findById($courseId, $postId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/posts/' . $postId);
    }

    public function updateApi(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->update($courseId, $postId, $userId, $membership, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Post not found'], true) ? 404 : ($result['error'] === 'Forbidden' ? 403 : 422);
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $postId, int $userId, array $membership, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        if (!can_manage_course_content($membership, $userId, (int) $post['author_id'])) {
            return ['success' => false, 'error' => 'Forbidden'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['success' => false, 'error' => 'title and body are required'];
        }
        if (can_moderate_course_content($membership)) {
            $this->postRepo->update($courseId, $postId, ['title' => $title, 'body' => $body]);
        } else {
            $this->postRepo->updateOwned($courseId, $postId, $userId, ['title' => $title, 'body' => $body]);
        }
        return ['success' => true, 'post' => $this->postRepo->findById($courseId, $postId)];
    }

    public function deleteHtml(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $userId = require_login($this->config, false);
        $this->delete($courseId, $postId, $userId, $membership);
        redirect_to('/courses/' . $courseId . '/posts');
    }

    public function deleteApi(int $courseId, int $postId): void
    {
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $userId = require_login($this->config, true);
        $result = $this->delete($courseId, $postId, $userId, $membership);
        if (!$result['success']) {
            json_response($result, $result['error'] === 'Forbidden' ? 403 : 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $postId, int $userId, array $membership): array
    {
        $post = $this->postRepo->findById($courseId, $postId);
        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        if (!can_manage_course_content($membership, $userId, (int) $post['author_id'])) {
            return ['success' => false, 'error' => 'Forbidden'];
        }
        if (can_moderate_course_content($membership)) {
            $this->postRepo->delete($courseId, $postId);
        } else {
            $this->postRepo->deleteOwned($courseId, $postId, $userId);
        }
        return ['success' => true];
    }
}