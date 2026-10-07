<?php
require_once APP_BASE . '/src/repos/PostRepo.php';
require_once APP_BASE . '/src/repos/CommentRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class SearchController {
    public function __construct(private PDO $pdo) {}

    public function searchPosts(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $keyword = trim($_GET['keyword'] ?? '');
        $posts = (new PostRepo($this->pdo))->searchPosts($courseId, $keyword);
        render('posts/search', ['title' => 'Search Posts', 'course_id' => $courseId, 'keyword' => $keyword, 'posts' => $posts]);
    }

    public function searchComments(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $keyword = trim($_GET['keyword'] ?? '');
        $comments = (new CommentRepo($this->pdo))->searchComments($courseId, $keyword);
        render('comments/search', ['title' => 'Search Comments', 'course_id' => $courseId, 'keyword' => $keyword, 'comments' => $comments]);
    }

    // API
    public function apiSearchPosts(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $keyword = trim($_GET['keyword'] ?? '');
        $posts = (new PostRepo($this->pdo))->searchPosts($courseId, $keyword);
        json_response(['posts' => $posts, 'keyword' => $keyword]);
    }

    public function apiSearchComments(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $keyword = trim($_GET['keyword'] ?? '');
        $comments = (new CommentRepo($this->pdo))->searchComments($courseId, $keyword);
        json_response(['comments' => $comments, 'keyword' => $keyword]);
    }
}

