<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use LMS\Repository\DiscussionRepository;
use LMS\Repository\EnrollmentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DiscussionBoardController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private DiscussionRepository $discussions,
        private EnrollmentRepository $enrollments,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course || !$this->isMember($user, $course)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $params = $request->getQueryParams();
        $search = trim((string)($params['q'] ?? ''));
        $threads = $this->discussions->threadsForCourse((int)$course['id'], $search);
        $threadData = [];
        foreach ($threads as $t) {
            $replies = $this->discussions->replies((int)$t['id']);
            $threadData[] = ['thread' => $t, 'replies' => $replies];
        }
        return $this->view->render($response, 'discussion.php', [
            'course' => $course,
            'threads' => $threadData,
            'search' => $search,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'flash' => ['notice' => $this->session->takeFlash('notice'), 'error' => $this->session->takeFlash('error')],
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course || !$this->isMember($user, $course)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $text = trim((string)($body['body'] ?? ''));
            if ($text === '') {
                $errors['body'] = 'Message body is required.';
            } else {
                $this->discussions->create((int)$course['id'], null, (int)$user['id'], $text);
                $this->session->setFlash('notice', 'Thread posted.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/discussion')->withStatus(302);
            }
        }
        return $this->view->render($response, 'discussion_form.php', [
            'course' => $course,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function reply(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $thread = $this->discussions->find((int)$args['id']);
        if (!$thread) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Thread not found.', 'status' => 404]);
        }
        $course = $this->courses->find((int)$thread['course_id']);
        if (!$this->isMember($user, $course)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        // Reuse POST handler inline
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $text = trim((string)($body['body'] ?? ''));
            if ($text !== '') {
                $this->discussions->create((int)$course['id'], (int)$thread['id'], (int)$user['id'], $text);
                $this->session->setFlash('notice', 'Reply posted.');
            }
            return $response->withHeader('Location', '/courses/' . $course['id'] . '/discussion')->withStatus(302);
        }
        return $this->view->render($response, 'discussion_reply.php', [
            'thread' => $thread,
            'course' => $course,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $post = $this->discussions->find((int)$args['id']);
        if (!$post) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Post not found.', 'status' => 404]);
        }
        if (!$user || ((int)$post['author_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        if (strtoupper($request->getMethod()) === 'POST') {
            $text = trim((string)((array)$request->getParsedBody())['body'] ?? '');
            if ($text === '') {
                $errors['body'] = 'Body is required.';
            } else {
                $this->discussions->update((int)$post['id'], $text);
                $this->session->setFlash('notice', 'Post updated.');
                return $response->withHeader('Location', '/courses/' . $post['course_id'] . '/discussion')->withStatus(302);
            }
        }
        return $this->view->render($response, 'discussion_edit.php', [
            'post' => $post,
            'course' => $this->courses->find((int)$post['course_id']),
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $post = $this->discussions->find((int)$args['id']);
        if ($post && $user && ((int)$post['author_id'] === (int)$user['id'] || $user['role'] === 'admin')) {
            $courseId = (int)$post['course_id'];
            $this->discussions->delete((int)$post['id']);
            $this->session->setFlash('notice', 'Post removed.');
            return $response->withHeader('Location', '/courses/' . $courseId . '/discussion')->withStatus(302);
        }
        return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
    }

    private function isMember(?array $user, ?array $course): bool
    {
        if (!$user || !$course) return false;
        if ($user['role'] === 'admin') return true;
        if ((int)$course['instructor_id'] === (int)$user['id']) return true;
        return $this->enrollments->isEnrolled((int)$user['id'], (int)$course['id']);
    }
}
