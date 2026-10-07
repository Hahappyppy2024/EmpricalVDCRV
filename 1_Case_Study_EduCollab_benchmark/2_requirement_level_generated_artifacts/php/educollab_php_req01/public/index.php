<?php

declare(strict_types=1);

session_start();

$config = require dirname(__DIR__) . '/config/config.php';

require_once dirname(__DIR__) . '/app/core/Bootstrap.php';
require_once dirname(__DIR__) . '/app/core/View.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/CourseController.php';

init_db($config);
seed_admin($config);

$pdo = Database::connection($config);
$userRepo = new UserRepository($pdo);
$courseRepo = new CourseRepository($pdo);
$authController = new AuthController($userRepo, $config);
$courseController = new CourseController($courseRepo, $config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' && $method === 'GET') {
    render('home', ['title' => 'EduCollab PHP']);
    exit;
}

if ($path === '/register' && $method === 'GET') {
    $authController->showRegister();
    exit;
}
if ($path === '/register' && $method === 'POST') {
    $authController->registerHtml();
    exit;
}
if ($path === '/login' && $method === 'GET') {
    $authController->showLogin();
    exit;
}
if ($path === '/login' && $method === 'POST') {
    $authController->loginHtml();
    exit;
}
if ($path === '/logout' && $method === 'POST') {
    $authController->logoutHtml();
    exit;
}
if ($path === '/me' && $method === 'GET') {
    $authController->meHtml();
    exit;
}
if ($path === '/admin/users' && $method === 'GET') {
    $authController->listUsersHtml();
    exit;
}
if ($path === '/courses' && $method === 'GET') {
    $courseController->listHtml();
    exit;
}
if ($path === '/courses/new' && $method === 'GET') {
    $courseController->showCreateForm();
    exit;
}
if ($path === '/courses' && $method === 'POST') {
    $courseController->createHtml();
    exit;
}
if (preg_match('#^/courses/(\d+)$#', $path, $matches) && $method === 'GET') {
    $courseController->getHtml((int) $matches[1]);
    exit;
}
if (preg_match('#^/courses/(\d+)/edit$#', $path, $matches) && $method === 'GET') {
    $courseController->showEditForm((int) $matches[1]);
    exit;
}
if (preg_match('#^/courses/(\d+)$#', $path, $matches) && $method === 'POST') {
    $courseController->updateHtml((int) $matches[1]);
    exit;
}
if (preg_match('#^/courses/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
    $courseController->deleteHtml((int) $matches[1]);
    exit;
}

if ($path === '/api/auth/register' && $method === 'POST') {
    $authController->registerApi();
    exit;
}
if ($path === '/api/auth/login' && $method === 'POST') {
    $authController->loginApi();
    exit;
}
if ($path === '/api/auth/logout' && $method === 'POST') {
    $authController->logoutApi();
    exit;
}
if ($path === '/api/auth/me' && $method === 'GET') {
    $authController->meApi();
    exit;
}
if ($path === '/api/users' && $method === 'GET') {
    $authController->listUsersApi();
    exit;
}
if ($path === '/api/courses' && $method === 'GET') {
    $courseController->listApi();
    exit;
}
if ($path === '/api/courses' && $method === 'POST') {
    $courseController->createApi();
    exit;
}
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'GET') {
    $courseController->getApi((int) $matches[1]);
    exit;
}
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $courseController->updateApi((int) $matches[1]);
    exit;
}
if (preg_match('#^/api/courses/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $courseController->deleteApi((int) $matches[1]);
    exit;
}

http_response_code(404);
if (str_starts_with($path, '/api/')) {
    json_response(['success' => false, 'error' => 'Not found'], 404);
}
render('home', ['title' => 'Not Found']);
