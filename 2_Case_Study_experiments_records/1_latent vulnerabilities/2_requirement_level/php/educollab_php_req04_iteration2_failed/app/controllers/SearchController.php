<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class SearchController
{
    public function __construct(
        private PostRepository $postRepo,
        private CommentRepository $commentRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function searchPostsHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        render('courses/search/posts', ['title' => 'Search Posts', 'course' => $this->courseRepo->findById($courseId), 'keyword' => $keyword, 'results' => $keyword === '' ? [] : $this->postRepo->searchByKeyword($courseId, $keyword)]);
    }

    public function searchPostsApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        json_response(['success' => true, 'keyword' => $keyword, 'posts' => $keyword === '' ? [] : $this->postRepo->searchByKeyword($courseId, $keyword)]);
    }

    public function searchCommentsHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        render('courses/search/comments', ['title' => 'Search Comments', 'course' => $this->courseRepo->findById($courseId), 'keyword' => $keyword, 'results' => $keyword === '' ? [] : $this->commentRepo->searchByKeyword($courseId, $keyword)]);
    }

    public function searchCommentsApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        json_response(['success' => true, 'keyword' => $keyword, 'comments' => $keyword === '' ? [] : $this->commentRepo->searchByKeyword($courseId, $keyword)]);
    }
}
