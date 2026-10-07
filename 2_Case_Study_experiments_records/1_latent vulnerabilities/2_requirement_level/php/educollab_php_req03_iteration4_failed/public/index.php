<?php

declare(strict_types=1);

session_start();

$config = require dirname(__DIR__) . '/config/config.php';

require_once dirname(__DIR__) . '/app/core/Bootstrap.php';
require_once dirname(__DIR__) . '/app/core/View.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/CourseController.php';
require_once dirname(__DIR__) . '/app/controllers/MembershipController.php';
require_once dirname(__DIR__) . '/app/controllers/PostController.php';
require_once dirname(__DIR__) . '/app/controllers/CommentController.php';
require_once dirname(__DIR__) . '/app/controllers/SearchController.php';

init_db($config);
seed_admin($config);
backfill_course_creator_memberships($config);

$pdo = Database::connection($config);
$userRepo = new UserRepository($pdo);
$courseRepo = new CourseRepository($pdo);
$membershipRepo = new MembershipRepository($pdo);
$postRepo = new PostRepository($pdo);
$commentRepo = new CommentRepository($pdo);
$authController = new AuthController($userRepo, $config);
$courseController = new CourseController($courseRepo, $membershipRepo, $config);
$membershipController = new MembershipController($membershipRepo, $courseRepo, $userRepo, $config);
$postController = new PostController($postRepo, $commentRepo, $courseRepo, $membershipRepo, $config);
$commentController = new CommentController($commentRepo, $postRepo, $courseRepo, $membershipRepo, $config);
$searchController = new SearchController($postRepo, $commentRepo, $courseRepo, $membershipRepo, $config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' && $method === 'GET') {
    render('home', ['title' => 'EduCollab PHP']);
    exit;
}

if ($path === '/register' && $method === 'GET') { $authController->showRegister(); exit; }
if ($path === '/register' && $method === 'POST') { $authController->registerHtml(); exit; }
if ($path === '/login' && $method === 'GET') { $authController->showLogin(); exit; }
if ($path === '/login' && $method === 'POST') { $authController->loginHtml(); exit; }
if ($path === '/logout' && $method === 'POST') { $authController->logoutHtml(); exit; }
if ($path === '/me' && $method === 'GET') { $authController->meHtml(); exit; }
if ($path === '/admin/users' && $method === 'GET') { $authController->listUsersHtml(); exit; }
if ($path === '/memberships' && $method === 'GET') { $membershipController->myMembershipsHtml(); exit; }
if ($path === '/courses' && $method === 'GET') { $courseController->listHtml(); exit; }
if ($path === '/courses/new' && $method === 'GET') { $courseController->showCreateForm(); exit; }
if ($path === '/courses' && $method === 'POST') { $courseController->createHtml(); exit; }
if (preg_match('#^/courses/(\d+)$#', $path, $matches) && $method === 'GET') { $courseController->getHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/edit$#', $path, $matches) && $method === 'GET') { $courseController->showEditForm((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)$#', $path, $matches) && $method === 'POST') { $courseController->updateHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/delete$#', $path, $matches) && $method === 'POST') { $courseController->deleteHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/members$#', $path, $matches) && $method === 'GET') { $membershipController->listMembersHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/members/new$#', $path, $matches) && $method === 'GET') { $membershipController->showAddForm((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/members$#', $path, $matches) && $method === 'POST') { $membershipController->addMemberHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/members/(\d+)$#', $path, $matches) && $method === 'POST') { $membershipController->updateMemberRoleHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/members/(\d+)/delete$#', $path, $matches) && $method === 'POST') { $membershipController->removeMemberHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts$#', $path, $matches) && $method === 'GET') { $postController->listHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts/new$#', $path, $matches) && $method === 'GET') { $postController->showCreateForm((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts$#', $path, $matches) && $method === 'POST') { $postController->createHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)$#', $path, $matches) && $method === 'GET') { $postController->getHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/edit$#', $path, $matches) && $method === 'GET') { $postController->showEditForm((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)$#', $path, $matches) && $method === 'POST') { $postController->updateHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/delete$#', $path, $matches) && $method === 'POST') { $postController->deleteHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments$#', $path, $matches) && $method === 'GET') { $commentController->listHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments$#', $path, $matches) && $method === 'POST') { $commentController->createHtml((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $matches) && $method === 'POST') { $commentController->updateHtml((int) $matches[1], (int) $matches[2], (int) $matches[3]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments/(\d+)/delete$#', $path, $matches) && $method === 'POST') { $commentController->deleteHtml((int) $matches[1], (int) $matches[2], (int) $matches[3]); exit; }
if (preg_match('#^/courses/(\d+)/search$#', $path, $matches) && $method === 'GET') { $searchController->searchPostsHtml((int) $matches[1]); exit; }
if (preg_match('#^/courses/(\d+)/search/comments$#', $path, $matches) && $method === 'GET') { $searchController->searchCommentsHtml((int) $matches[1]); exit; }

if ($path === '/api/auth/register' && $method === 'POST') { $authController->registerApi(); exit; }
if ($path === '/api/auth/login' && $method === 'POST') { $authController->loginApi(); exit; }
if ($path === '/api/auth/logout' && $method === 'POST') { $authController->logoutApi(); exit; }
if ($path === '/api/auth/me' && $method === 'GET') { $authController->meApi(); exit; }
if ($path === '/api/users' && $method === 'GET') { $authController->listUsersApi(); exit; }
if ($path === '/api/memberships' && $method === 'GET') { $membershipController->myMembershipsApi(); exit; }
if ($path === '/api/courses' && $method === 'GET') { $courseController->listApi(); exit; }
if ($path === '/api/courses' && $method === 'POST') { $courseController->createApi(); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'GET') { $courseController->getApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'PUT') { $courseController->updateApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'DELETE') { $courseController->deleteApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/members$#', $path, $matches) && $method === 'GET') { $membershipController->listMembersApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/members$#', $path, $matches) && $method === 'POST') { $membershipController->addMemberApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/members/(\d+)$#', $path, $matches) && $method === 'PUT') { $membershipController->updateMemberRoleApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/members/(\d+)$#', $path, $matches) && $method === 'DELETE') { $membershipController->removeMemberApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts$#', $path, $matches) && $method === 'GET') { $postController->listApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts$#', $path, $matches) && $method === 'POST') { $postController->createApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $matches) && $method === 'GET') { $postController->getApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $matches) && $method === 'PUT') { $postController->updateApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $matches) && $method === 'DELETE') { $postController->deleteApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments$#', $path, $matches) && $method === 'GET') { $commentController->listApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments$#', $path, $matches) && $method === 'POST') { $commentController->createApi((int) $matches[1], (int) $matches[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $matches) && $method === 'PUT') { $commentController->updateApi((int) $matches[1], (int) $matches[2], (int) $matches[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $matches) && $method === 'DELETE') { $commentController->deleteApi((int) $matches[1], (int) $matches[2], (int) $matches[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/search/posts$#', $path, $matches) && $method === 'GET') { $searchController->searchPostsApi((int) $matches[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/search/comments$#', $path, $matches) && $method === 'GET') { $searchController->searchCommentsApi((int) $matches[1]); exit; }

http_response_code(404);
if (str_starts_with($path, '/api/')) {
    json_response(['success' => false, 'error' => 'Not found'], 404);
}
render('home', ['title' => 'Not Found', 'message' => 'Requested page not found']);
