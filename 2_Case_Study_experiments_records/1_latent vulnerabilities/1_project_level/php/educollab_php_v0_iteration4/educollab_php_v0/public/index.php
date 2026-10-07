<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Router.php';

// Controllers
require_once APP_BASE . '/src/controllers/AuthController.php';
require_once APP_BASE . '/src/controllers/UserController.php';
require_once APP_BASE . '/src/controllers/CourseController.php';
require_once APP_BASE . '/src/controllers/MembershipController.php';
require_once APP_BASE . '/src/controllers/PostController.php';
require_once APP_BASE . '/src/controllers/CommentController.php';
require_once APP_BASE . '/src/controllers/SearchController.php';
require_once APP_BASE . '/src/controllers/AssignmentController.php';
require_once APP_BASE . '/src/controllers/SubmissionController.php';
require_once APP_BASE . '/src/controllers/UploadController.php';
require_once APP_BASE . '/src/controllers/QuestionController.php';
require_once APP_BASE . '/src/controllers/QuizController.php';
require_once APP_BASE . '/src/controllers/TakeQuizController.php';

$router = new Router();
$pdo = db();

$auth = new AuthController($pdo);
$users = new UserController($pdo);
$courses = new CourseController($pdo);
$members = new MembershipController($pdo);
$posts = new PostController($pdo);
$comments = new CommentController($pdo);
$search = new SearchController($pdo);
$assignments = new AssignmentController($pdo);
$submissions = new SubmissionController($pdo);
$uploads = new UploadController($pdo);
$questions = new QuestionController($pdo);
$quizzes = new QuizController($pdo);
$takeQuiz = new TakeQuizController($pdo);

// Home
$router->add('GET', '/', function() {
    if (!empty($_SESSION['user'])) redirect('/courses');
    redirect('/login');
});

// Auth HTML
$router->add('GET', '/register', fn() => $auth->showRegister());
$router->add('POST', '/register', fn() => $auth->register());
$router->add('GET', '/login', fn() => $auth->showLogin());
$router->add('POST', '/login', fn() => $auth->login());
$router->add('POST', '/logout', fn() => $auth->logout());
$router->add('GET', '/me', fn() => $auth->me());

// Users
$router->add('GET', '/admin/users', fn() => $users->adminList());

// Courses HTML
$router->add('GET', '/courses', fn() => $courses->list());
$router->add('GET', '/courses/new', fn() => $courses->showNew());
$router->add('POST', '/courses', fn() => $courses->create());
$router->add('GET', '/courses/(?P<course_id>\d+)', fn($p) => $courses->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/edit', fn($p) => $courses->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)', fn($p) => $courses->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/delete', fn($p) => $courses->delete($p));

// Memberships HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/members', fn($p) => $members->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/members/new', fn($p) => $members->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members', fn($p) => $members->add($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->updateRole($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)/delete', fn($p) => $members->remove($p));

// Posts HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/posts/new', fn($p) => $posts->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/posts', fn($p) => $posts->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)', fn($p) => $posts->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/edit', fn($p) => $posts->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)', fn($p) => $posts->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/delete', fn($p) => $posts->delete($p));

// Comments HTML
$router->add('POST', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments', fn($p) => $comments->create($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments/(?P<comment_id>\d+)', fn($p) => $comments->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments/(?P<comment_id>\d+)/delete', fn($p) => $comments->delete($p));

// Search HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/search', fn($p) => $search->search($p));

// Assignments HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/new', fn($p) => $assignments->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/edit', fn($p) => $assignments->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/delete', fn($p) => $assignments->delete($p));

// Submissions HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submit', fn($p) => $submissions->showSubmit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submit', fn($p) => $submissions->submit($p));
$router->add('GET', '/me/submissions', fn() => $submissions->mySubmissions());

// Uploads HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads/new', fn($p) => $uploads->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->upload($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)', fn($p) => $uploads->download($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)/delete', fn($p) => $uploads->delete($p));

// Questions HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/questions/new', fn($p) => $questions->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)/edit', fn($p) => $questions->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)/delete', fn($p) => $questions->delete($p));

// Quizzes HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/new', fn($p) => $quizzes->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/edit', fn($p) => $quizzes->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/delete', fn($p) => $quizzes->delete($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->configureQuestions($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->addQuestion($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions/(?P<question_id>\d+)/delete', fn($p) => $quizzes->removeQuestion($p));

// Take Quiz HTML
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/start', fn($p) => $takeQuiz->start($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)', fn($p) => $takeQuiz->showAttempt($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/answers', fn($p) => $takeQuiz->answer($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/submit', fn($p) => $takeQuiz->submit($p));
$router->add('GET', '/me/quiz-attempts', fn() => $takeQuiz->myAttempts());

// Auth API
$router->add('POST', '/api/auth/register', fn() => $auth->apiRegister());
$router->add('POST', '/api/auth/login', fn() => $auth->apiLogin());
$router->add('POST', '/api/auth/logout', fn() => $auth->apiLogout());
$router->add('GET', '/api/me', fn() => $auth->apiMe());

// Users API
$router->add('GET', '/api/admin/users', fn() => $users->apiAdminList());

// Courses API
$router->add('GET', '/api/courses', fn() => $courses->apiList());
$router->add('POST', '/api/courses', fn() => $courses->apiCreate());
$router->add('GET', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiDelete($p));

// Memberships API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/members', fn($p) => $members->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/members', fn($p) => $members->apiAdd($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->apiUpdateRole($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->apiRemove($p));

// Posts API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/posts', fn($p) => $posts->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/posts', fn($p) => $posts->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)', fn($p) => $posts->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)', fn($p) => $posts->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)', fn($p) => $posts->apiDelete($p));

// Comments API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments', fn($p) => $comments->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments', fn($p) => $comments->apiCreate($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments/(?P<comment_id>\d+)', fn($p) => $comments->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/posts/(?P<post_id>\d+)/comments/(?P<comment_id>\d+)', fn($p) => $comments->apiDelete($p));

// Search API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/search', fn($p) => $search->apiSearch($p));

// Assignments API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiDelete($p));

// Submissions API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->apiListForAssignment($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->apiCreate($p));
$router->add('GET', '/api/my/submissions', fn() => $submissions->apiListMine());
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)', fn($p) => $submissions->apiUpdateMine($p));

// Uploads API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->apiUpload($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)', fn($p) => $uploads->apiGet($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)', fn($p) => $uploads->apiDelete($p));

// Questions API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiDelete($p));

// Quizzes API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiDelete($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->apiListQuestions($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->apiAddQuestion($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $quizzes->apiRemoveQuestion($p));

// Take Quiz API
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/start', fn($p) => $takeQuiz->apiStart($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)', fn($p) => $takeQuiz->apiGetAttempt($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/answers', fn($p) => $takeQuiz->apiAnswer($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/submit', fn($p) => $takeQuiz->apiSubmit($p));
$router->add('GET', '/api/me/quiz-attempts', fn() => $takeQuiz->apiMyAttempts());

// Dispatch
$method = method_override();
$path = request_path();
if ($method === 'POST' && strncmp($path, '/api/', 5) !== 0) {
    require_csrf();
}
$router->dispatch($method, $path);