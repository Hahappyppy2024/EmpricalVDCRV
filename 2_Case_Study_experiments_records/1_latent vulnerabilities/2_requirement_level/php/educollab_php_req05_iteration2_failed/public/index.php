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
require_once dirname(__DIR__) . '/app/controllers/AssignmentController.php';
require_once dirname(__DIR__) . '/app/controllers/SubmissionController.php';
require_once dirname(__DIR__) . '/app/controllers/UploadController.php';
require_once dirname(__DIR__) . '/app/controllers/QuestionController.php';
require_once dirname(__DIR__) . '/app/controllers/QuizController.php';
require_once dirname(__DIR__) . '/app/controllers/QuizAttemptController.php';

init_db($config);
seed_admin($config);
backfill_course_creator_memberships($config);

$storagePath = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0777, true);
}

$pdo = Database::connection($config);
$userRepo = new UserRepository($pdo);
$courseRepo = new CourseRepository($pdo);
$membershipRepo = new MembershipRepository($pdo);
$postRepo = new PostRepository($pdo);
$commentRepo = new CommentRepository($pdo);
$assignmentRepo = new AssignmentRepository($pdo);
$submissionRepo = new SubmissionRepository($pdo);
$uploadRepo = new UploadRepository($pdo);

$questionRepo = new QuestionRepository($pdo);
$quizRepo = new QuizRepository($pdo);
$quizAttemptRepo = new QuizAttemptRepository($pdo);
$authController = new AuthController($userRepo, $config);
$courseController = new CourseController($courseRepo, $membershipRepo, $config);
$membershipController = new MembershipController($membershipRepo, $courseRepo, $userRepo, $config);
$postController = new PostController($postRepo, $commentRepo, $courseRepo, $membershipRepo, $config);
$commentController = new CommentController($commentRepo, $postRepo, $courseRepo, $membershipRepo, $config);
$searchController = new SearchController($postRepo, $commentRepo, $courseRepo, $membershipRepo, $config);
$assignmentController = new AssignmentController($assignmentRepo, $submissionRepo, $courseRepo, $membershipRepo, $config);
$submissionController = new SubmissionController($submissionRepo, $assignmentRepo, $uploadRepo, $courseRepo, $membershipRepo, $config);
$uploadController = new UploadController($uploadRepo, $courseRepo, $membershipRepo, $config);
$questionController = new QuestionController($questionRepo, $courseRepo, $membershipRepo, $config);
$quizController = new QuizController($quizRepo, $questionRepo, $quizAttemptRepo, $courseRepo, $membershipRepo, $config);
$quizAttemptController = new QuizAttemptController($quizAttemptRepo, $quizRepo, $courseRepo, $membershipRepo, $config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' && $method === 'GET') { render('home', ['title' => 'EduCollab PHP']); exit; }

if ($path === '/register' && $method === 'GET') { $authController->showRegister(); exit; }
if ($path === '/register' && $method === 'POST') { $authController->registerHtml(); exit; }
if ($path === '/login' && $method === 'GET') { $authController->showLogin(); exit; }
if ($path === '/login' && $method === 'POST') { $authController->loginHtml(); exit; }
if ($path === '/logout' && $method === 'POST') { $authController->logoutHtml(); exit; }
if ($path === '/me' && $method === 'GET') { $authController->meHtml(); exit; }
if ($path === '/admin/users' && $method === 'GET') { $authController->listUsersHtml(); exit; }
if ($path === '/memberships' && $method === 'GET') { $membershipController->myMembershipsHtml(); exit; }
if ($path === '/my/submissions' && $method === 'GET') { $submissionController->listMyHtml(); exit; }

if ($path === '/courses' && $method === 'GET') { $courseController->listHtml(); exit; }
if ($path === '/courses/new' && $method === 'GET') { $courseController->showCreateForm(); exit; }
if ($path === '/courses' && $method === 'POST') { $courseController->createHtml(); exit; }
if (preg_match('#^/courses/(\d+)$#', $path, $m) && $method === 'GET') { $courseController->getHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/edit$#', $path, $m) && $method === 'GET') { $courseController->showEditForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)$#', $path, $m) && $method === 'POST') { $courseController->updateHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/delete$#', $path, $m) && $method === 'POST') { $courseController->deleteHtml((int) $m[1]); exit; }

if (preg_match('#^/courses/(\d+)/members$#', $path, $m) && $method === 'GET') { $membershipController->listMembersHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/members/new$#', $path, $m) && $method === 'GET') { $membershipController->showAddForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/members$#', $path, $m) && $method === 'POST') { $membershipController->addMemberHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/members/(\d+)$#', $path, $m) && $method === 'POST') { $membershipController->updateMemberRoleHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/members/(\d+)/delete$#', $path, $m) && $method === 'POST') { $membershipController->removeMemberHtml((int) $m[1], (int) $m[2]); exit; }

if (preg_match('#^/courses/(\d+)/posts$#', $path, $m) && $method === 'GET') { $postController->listHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts/new$#', $path, $m) && $method === 'GET') { $postController->showCreateForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts$#', $path, $m) && $method === 'POST') { $postController->createHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)$#', $path, $m) && $method === 'GET') { $postController->getHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/edit$#', $path, $m) && $method === 'GET') { $postController->showEditForm((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)$#', $path, $m) && $method === 'POST') { $postController->updateHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/delete$#', $path, $m) && $method === 'POST') { $postController->deleteHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments$#', $path, $m) && $method === 'GET') { $commentController->listHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments$#', $path, $m) && $method === 'POST') { $commentController->createHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $m) && $method === 'POST') { $commentController->updateHtml((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/courses/(\d+)/posts/(\d+)/comments/(\d+)/delete$#', $path, $m) && $method === 'POST') { $commentController->deleteHtml((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/courses/(\d+)/search$#', $path, $m) && $method === 'GET') { $searchController->searchPostsHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/search/comments$#', $path, $m) && $method === 'GET') { $searchController->searchCommentsHtml((int) $m[1]); exit; }

if (preg_match('#^/courses/(\d+)/assignments$#', $path, $m) && $method === 'GET') { $assignmentController->listHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/new$#', $path, $m) && $method === 'GET') { $assignmentController->showCreateForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/assignments$#', $path, $m) && $method === 'POST') { $assignmentController->createHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)$#', $path, $m) && $method === 'GET') { $assignmentController->getHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/edit$#', $path, $m) && $method === 'GET') { $assignmentController->showEditForm((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)$#', $path, $m) && $method === 'POST') { $assignmentController->updateHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/delete$#', $path, $m) && $method === 'POST') { $assignmentController->deleteHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/submit$#', $path, $m) && $method === 'GET') { $submissionController->showSubmitForm((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/submissions$#', $path, $m) && $method === 'POST') { $submissionController->createHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/submissions$#', $path, $m) && $method === 'GET') { $submissionController->listForAssignmentHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/assignments/(\d+)/submissions/(\d+)$#', $path, $m) && $method === 'POST') { $submissionController->updateHtml((int) $m[1], (int) $m[2], (int) $m[3]); exit; }

if (preg_match('#^/courses/(\d+)/uploads$#', $path, $m) && $method === 'GET') { $uploadController->listHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/uploads/new$#', $path, $m) && $method === 'GET') { $uploadController->showUploadForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/uploads$#', $path, $m) && $method === 'POST') { $uploadController->uploadHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/uploads/(\d+)/download$#', $path, $m) && $method === 'GET') { $uploadController->downloadHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/uploads/(\d+)/delete$#', $path, $m) && $method === 'POST') { $uploadController->deleteHtml((int) $m[1], (int) $m[2]); exit; }


if ($path === '/my/quizzes' && $method === 'GET') { $quizAttemptController->listMyHtml(); exit; }

if (preg_match('#^/courses/(\d+)/questions$#', $path, $m) && $method === 'GET') { $questionController->listHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/questions/new$#', $path, $m) && $method === 'GET') { $questionController->showCreateForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/questions$#', $path, $m) && $method === 'POST') { $questionController->createHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'GET') { $questionController->getHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/questions/(\d+)/edit$#', $path, $m) && $method === 'GET') { $questionController->showEditForm((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'POST') { $questionController->updateHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/questions/(\d+)/delete$#', $path, $m) && $method === 'POST') { $questionController->deleteHtml((int) $m[1], (int) $m[2]); exit; }

if (preg_match('#^/courses/(\d+)/quizzes$#', $path, $m) && $method === 'GET') { $quizController->listHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/new$#', $path, $m) && $method === 'GET') { $quizController->showCreateForm((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes$#', $path, $m) && $method === 'POST') { $quizController->createHtml((int) $m[1]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)$#', $path, $m) && $method === 'GET') { $quizController->getHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/edit$#', $path, $m) && $method === 'GET') { $quizController->showEditForm((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)$#', $path, $m) && $method === 'POST') { $quizController->updateHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/delete$#', $path, $m) && $method === 'POST') { $quizController->deleteHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/questions$#', $path, $m) && $method === 'GET') { $quizController->getHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/questions$#', $path, $m) && $method === 'POST') { $quizController->configureQuestionsHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/start$#', $path, $m) && $method === 'POST') { $quizAttemptController->startHtml((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/attempts/(\d+)/answers$#', $path, $m) && $method === 'POST') { $quizAttemptController->answerHtml((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/courses/(\d+)/quizzes/(\d+)/attempts/(\d+)/submit$#', $path, $m) && $method === 'POST') { $quizAttemptController->submitHtml((int) $m[1], (int) $m[2], (int) $m[3]); exit; }

if ($path === '/api/auth/register' && $method === 'POST') { $authController->registerApi(); exit; }
if ($path === '/api/auth/login' && $method === 'POST') { $authController->loginApi(); exit; }
if ($path === '/api/auth/logout' && $method === 'POST') { $authController->logoutApi(); exit; }
if ($path === '/api/auth/me' && $method === 'GET') { $authController->meApi(); exit; }
if ($path === '/api/users' && $method === 'GET') { $authController->listUsersApi(); exit; }
if ($path === '/api/memberships' && $method === 'GET') { $membershipController->myMembershipsApi(); exit; }

if ($path === '/api/my/quizzes/attempts' && $method === 'GET') { $quizAttemptController->listMyApi(); exit; }

if (preg_match('#^/api/courses/(\d+)/questions$#', $path, $m) && $method === 'GET') { $questionController->listApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/questions$#', $path, $m) && $method === 'POST') { $questionController->createApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'GET') { $questionController->getApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'PUT') { $questionController->updateApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'DELETE') { $questionController->deleteApi((int) $m[1], (int) $m[2]); exit; }

if (preg_match('#^/api/courses/(\d+)/quizzes$#', $path, $m) && $method === 'GET') { $quizController->listApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes$#', $path, $m) && $method === 'POST') { $quizController->createApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)$#', $path, $m) && $method === 'GET') { $quizController->getApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)$#', $path, $m) && $method === 'PUT') { $quizController->updateApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)$#', $path, $m) && $method === 'DELETE') { $quizController->deleteApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)/questions$#', $path, $m) && $method === 'POST') { $quizController->configureQuestionsApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)/questions/(\d+)$#', $path, $m) && $method === 'DELETE') { $quizController->deleteConfiguredQuestionApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)/attempts/start$#', $path, $m) && $method === 'POST') { $quizAttemptController->startApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)/attempts/(\d+)/answers$#', $path, $m) && $method === 'POST') { $quizAttemptController->answerApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/quizzes/(\d+)/attempts/(\d+)/submit$#', $path, $m) && $method === 'POST') { $quizAttemptController->submitApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if ($path === '/api/my/submissions' && $method === 'GET') { $submissionController->listMyApi(); exit; }

if ($path === '/api/courses' && $method === 'GET') { $courseController->listApi(); exit; }
if ($path === '/api/courses' && $method === 'POST') { $courseController->createApi(); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $m) && $method === 'GET') { $courseController->getApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $m) && $method === 'PUT') { $courseController->updateApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)$#', $path, $m) && $method === 'DELETE') { $courseController->deleteApi((int) $m[1]); exit; }

if (preg_match('#^/api/courses/(\d+)/members$#', $path, $m) && $method === 'GET') { $membershipController->listMembersApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/members$#', $path, $m) && $method === 'POST') { $membershipController->addMemberApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/members/(\d+)$#', $path, $m) && $method === 'PUT') { $membershipController->updateMemberRoleApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/members/(\d+)$#', $path, $m) && $method === 'DELETE') { $membershipController->removeMemberApi((int) $m[1], (int) $m[2]); exit; }

if (preg_match('#^/api/courses/(\d+)/posts$#', $path, $m) && $method === 'GET') { $postController->listApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts$#', $path, $m) && $method === 'POST') { $postController->createApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $m) && $method === 'GET') { $postController->getApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $m) && $method === 'PUT') { $postController->updateApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)$#', $path, $m) && $method === 'DELETE') { $postController->deleteApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments$#', $path, $m) && $method === 'GET') { $commentController->listApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments$#', $path, $m) && $method === 'POST') { $commentController->createApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $m) && $method === 'PUT') { $commentController->updateApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/posts/(\d+)/comments/(\d+)$#', $path, $m) && $method === 'DELETE') { $commentController->deleteApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }
if (preg_match('#^/api/courses/(\d+)/search/posts$#', $path, $m) && $method === 'GET') { $searchController->searchPostsApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/search/comments$#', $path, $m) && $method === 'GET') { $searchController->searchCommentsApi((int) $m[1]); exit; }

if (preg_match('#^/api/courses/(\d+)/assignments$#', $path, $m) && $method === 'GET') { $assignmentController->listApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments$#', $path, $m) && $method === 'POST') { $assignmentController->createApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)$#', $path, $m) && $method === 'GET') { $assignmentController->getApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)$#', $path, $m) && $method === 'PUT') { $assignmentController->updateApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)$#', $path, $m) && $method === 'DELETE') { $assignmentController->deleteApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)/submissions$#', $path, $m) && $method === 'POST') { $submissionController->createApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)/submissions$#', $path, $m) && $method === 'GET') { $submissionController->listForAssignmentApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/assignments/(\d+)/submissions/(\d+)$#', $path, $m) && $method === 'PUT') { $submissionController->updateApi((int) $m[1], (int) $m[2], (int) $m[3]); exit; }

if (preg_match('#^/api/courses/(\d+)/uploads$#', $path, $m) && $method === 'GET') { $uploadController->listApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/uploads$#', $path, $m) && $method === 'POST') { $uploadController->uploadApi((int) $m[1]); exit; }
if (preg_match('#^/api/courses/(\d+)/uploads/(\d+)/download$#', $path, $m) && $method === 'GET') { $uploadController->downloadApi((int) $m[1], (int) $m[2]); exit; }
if (preg_match('#^/api/courses/(\d+)/uploads/(\d+)$#', $path, $m) && $method === 'DELETE') { $uploadController->deleteApi((int) $m[1], (int) $m[2]); exit; }

http_response_code(404);
if (str_starts_with($path, '/api/')) {
    json_response(['success' => false, 'error' => 'Not found'], 404);
}
render('home', ['title' => 'Not Found', 'message' => 'Requested page not found']);
