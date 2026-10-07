<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Router.php';

error_log("InviteTokenRepo file exists? " . (file_exists(APP_BASE . '/src/repos/InviteTokenRepo.php') ? 'YES' : 'NO'));

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
require_once APP_BASE . '/src/controllers/MaterialsController.php';
require_once APP_BASE . '/src/controllers/CsvController.php';
require_once APP_BASE . '/src/controllers/AdminController.php';
require_once APP_BASE . '/src/controllers/InviteController.php';
require_once APP_BASE . '/src/controllers/_guards.php';


error_log("APP_BASE=" . APP_BASE);
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
$materials = new MaterialsController($pdo);
$csv = new CsvController($pdo);
$admin = new AdminController($pdo);
$invites= new InviteController($pdo);

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
// Admin Audit Log (A09)
$router->add('GET', '/admin/audit', fn() => $admin->auditLog());
$router->add('GET', '/api/admin/audit', fn() => $admin->apiListAudit());


// Courses HTML
$router->add('GET', '/courses', fn() => $courses->list());
$router->add('GET', '/courses/new', fn() => $courses->showNew());
$router->add('POST', '/courses', fn() => $courses->create());
$router->add('GET', '/courses/(?P<course_id>\d+)', fn($p) => $courses->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/edit', fn($p) => $courses->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)', fn($p) => $courses->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/delete', fn($p) => $courses->delete($p));

// Membership HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/members', fn($p) => $members->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/members/new', fn($p) => $members->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members', fn($p) => $members->add($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->updateRole($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)/delete', fn($p) => $members->remove($p));
$router->add('GET', '/memberships', fn() => $members->myMemberships());

// Posts HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/posts', fn($p) => $posts->list($p));
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
$router->add('GET', '/courses/(?P<course_id>\d+)/search', fn($p) => $search->searchPosts($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/search/comments', fn($p) => $search->searchComments($p));

// Assignments HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/new', fn($p) => $assignments->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/edit', fn($p) => $assignments->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/delete', fn($p) => $assignments->delete($p));

// Submissions HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submit', fn($p) => $submissions->showSubmit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->create($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)', fn($p) => $submissions->updateMine($p));
$router->add('GET', '/my/submissions', fn() => $submissions->mySubmissions());
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->listForAssignment($p));

// Uploads HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads/new', fn($p) => $uploads->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->upload($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)/download', fn($p) => $uploads->download($p));
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
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->list($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/new', fn($p) => $quizzes->showNew($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->create($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->detail($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/edit', fn($p) => $quizzes->showEdit($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->update($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/delete', fn($p) => $quizzes->delete($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->showConfigureQuestions($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->addQuizQuestion($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions/(?P<question_id>\d+)/delete', fn($p) => $quizzes->removeQuizQuestion($p));

// Student take quiz HTML
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/start', fn($p) => $takeQuiz->start($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)', fn($p) => $takeQuiz->showAttempt($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/answers', fn($p) => $takeQuiz->answer($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/submit', fn($p) => $takeQuiz->submit($p));
$router->add('GET', '/my/quizzes', fn() => $takeQuiz->myAttempts());

// ---------------------- API routes ----------------------



// Materials HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/materials', fn($p) => $materials->index($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/materials/upload-zip', fn($p) => $materials->showUploadZip($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/materials/upload-zip', fn($p) => $materials->uploadZip($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/materials/download-zip', fn($p) => $materials->downloadZip($p));

// CSV HTML
$router->add('GET', '/courses/(?P<course_id>\d+)/members/export.csv', fn($p) => $csv->exportMembers($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/members/import.csv', fn($p) => $csv->importMembers($p));
$router->add('GET', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/grades.csv', fn($p) => $csv->exportAssignmentGrades($p));
$router->add('POST', '/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/grades/import.csv', fn($p) => $csv->importAssignmentGrades($p));

$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/grades/import.csv', fn($p) => $csv->apiImportAssignmentGrades($p));


// Auth API
$router->add('POST', '/api/auth/register', fn() => $auth->apiRegister());
$router->add('POST', '/api/auth/login', fn() => $auth->apiLogin());
$router->add('POST', '/api/auth/logout', fn() => $auth->apiLogout());
$router->add('GET', '/api/auth/me', fn() => $auth->apiMe());

// Users API
$router->add('GET', '/api/users', fn() => $users->apiList());

// Courses API
$router->add('GET', '/api/courses', fn() => $courses->apiList());
$router->add('POST', '/api/courses', fn() => $courses->apiCreate());
$router->add('GET', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)', fn($p) => $courses->apiDelete($p));

// Membership API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/members', fn($p) => $members->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/members', fn($p) => $members->apiAdd($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->apiUpdateRole($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/members/(?P<membership_id>\d+)', fn($p) => $members->apiRemove($p));
$router->add('GET', '/api/memberships', fn() => $members->apiMyMemberships());

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
$router->add('GET', '/api/courses/(?P<course_id>\d+)/search/posts', fn($p) => $search->apiSearchPosts($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/search/comments', fn($p) => $search->apiSearchComments($p));

// Assignments API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments', fn($p) => $assignments->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)', fn($p) => $assignments->apiDelete($p));

// Submissions API
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->apiCreate($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)', fn($p) => $submissions->apiUpdate($p));
$router->add('GET', '/api/my/submissions', fn() => $submissions->apiListMine());
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions', fn($p) => $submissions->apiListForAssignment($p));



// Submissions zip/unzip API (v1)
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)/unzip', fn($p) => $submissions->apiUnzipSubmission($p));
$router->add('GET',  '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)/files', fn($p) => $submissions->apiListSubmissionFiles($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/export-zip', fn($p) => $submissions->apiExportSubmissionsZip($p));
$router->add('GET',  '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/export-zip/(?P<job_id>[A-Za-z0-9_.-]+)/download', fn($p) => $submissions->apiDownloadSubmissionsZip($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/submissions/(?P<submission_id>\d+)',
    fn($p) => $submissions->apiGetOne($p)
);
// Uploads API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/uploads', fn($p) => $uploads->apiUpload($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)/download', fn($p) => $uploads->apiDownload($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/uploads/(?P<upload_id>\d+)', fn($p) => $uploads->apiDelete($p));


// Question bank API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/questions', fn($p) => $questions->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $questions->apiDelete($p));

// Quiz API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->apiList($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes', fn($p) => $quizzes->apiCreate($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiGet($p));
$router->add('PUT', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiUpdate($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)', fn($p) => $quizzes->apiDelete($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions', fn($p) => $quizzes->apiAddQuizQuestion($p));
$router->add('DELETE', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/questions/(?P<question_id>\d+)', fn($p) => $quizzes->apiRemoveQuizQuestion($p));

// Student take quiz API
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/start', fn($p) => $takeQuiz->apiStart($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/answers', fn($p) => $takeQuiz->apiAnswer($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/quizzes/(?P<quiz_id>\d+)/attempts/(?P<attempt_id>\d+)/submit', fn($p) => $takeQuiz->apiSubmit($p));
$router->add('GET', '/api/my/quizzes/attempts', fn() => $takeQuiz->apiMyAttempts());


// Materials API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/materials/files', fn($p) => $materials->apiListFiles($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/materials/upload-zip', fn($p) => $materials->apiUploadZip($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/materials/download-zip', fn($p) => $materials->apiDownloadZip($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/materials', fn($p) => $materials->apiIndex($p));



// CSV API
$router->add('GET', '/api/courses/(?P<course_id>\d+)/members/export.csv', fn($p) => $csv->apiExportMembers($p));
$router->add('POST', '/api/courses/(?P<course_id>\d+)/members/import.csv', fn($p) => $csv->apiImportMembers($p));
$router->add('GET', '/api/courses/(?P<course_id>\d+)/assignments/(?P<assignment_id>\d+)/grades.csv', fn($p) => $csv->apiExportAssignmentGrades($p));


//audit log
$router->add('GET', '/admin/audit', fn() => $admin->auditLog());
$router->add('GET', '/api/admin/audit', fn() => $admin->apiListAudit());


//invite
$router->add('POST', '/api/courses/1/invites', fn() => json_response(['ok'=>true], 200));

$router->add('GET', '/api/__ping', function () {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'project' => 'code_for_vm/educollab_php', 'file' => __FILE__]);
});
// Invite API
$router->add('POST', '/api/courses/(?P<course_id>\d+)/invites', fn($p) => $invites->apiCreate($p));
$router->add('POST', '/api/join', fn() => $invites->apiJoin());
$router->add('GET',  '/api/join', fn() => $invites->apiJoin());
$router->add('GET',  '/join', fn() => $invites->joinPage());


// Dispatch
$method = method_override();
$path = request_path();
$router->dispatch($method, $path);


