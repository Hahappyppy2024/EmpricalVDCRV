<?php
declare(strict_types=1);

namespace App;

use App\Controllers\{
    AccountAccessController,
    DashboardController,
    ConferencePhasesController,
    PaperSubmissionController,
    SubmissionDiscoveryController,
    ManuscriptAccessController,
    ReviewerAssignmentController,
    ReviewingController,
    RebuttalController,
    DecisionManagementController,
    DoubleBlindViewsController,
    BulkExportsController,
    FrontendApiController,
    ApiRouterController
};
use App\Services\SessionService;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);
$config = Config::load($rootPath);

Database::init($config['db']['path']);
$GLOBALS['app_config'] = $config;

SessionService::start($config);

Schema::migrate(Database::pdo());

$app = AppFactory::create();

$app->get('/', [DashboardController::class, 'home']);
$app->get('/login', [AccountAccessController::class, 'showPage']);
$app->get('/account', [AccountAccessController::class, 'showPage']);
$app->post('/login', [AccountAccessController::class, 'login']);
$app->post('/register', [AccountAccessController::class, 'register']);
$app->post('/reset/request', [AccountAccessController::class, 'requestReset']);
$app->post('/reset/perform', [AccountAccessController::class, 'performReset']);
$app->get('/logout', [AccountAccessController::class, 'logout']);
$app->post('/logout', [AccountAccessController::class, 'logout']);

$app->get('/dashboard', [DashboardController::class, 'index']);

$app->get('/conference_phases', [ConferencePhasesController::class, 'index']);
$app->patch('/conference_phases/{id}', [ConferencePhasesController::class, 'update']);
$app->post('/conference_phases/{id}', [ConferencePhasesController::class, 'update']);
$app->get('/api/conf/conference_phases', [ConferencePhasesController::class, 'apiIndex']);

$app->get('/paper_submission', [PaperSubmissionController::class, 'index']);
$app->post('/paper_submission', [PaperSubmissionController::class, 'create']);
$app->patch('/paper_submission/{id}', [PaperSubmissionController::class, 'create']);

$app->get('/submission_discovery', [SubmissionDiscoveryController::class, 'index']);

$app->get('/manuscript_access/{submission_id}', [ManuscriptAccessController::class, 'list']);
$app->get('/manuscript_access/{submission_id}/{file_id}/download', [ManuscriptAccessController::class, 'download']);

$app->get('/reviewer_assignment', [ReviewerAssignmentController::class, 'index']);
$app->post('/reviewer_assignment/assign', [ReviewerAssignmentController::class, 'assign']);
$app->post('/reviewer_assignment/conflict', [ReviewerAssignmentController::class, 'declareConflict']);
$app->post('/reviewer_assignment/{id}/remove', [ReviewerAssignmentController::class, 'removeAssignment']);

$app->get('/reviewing', [ReviewingController::class, 'index']);
$app->get('/reviewing/{assignment_id}', [ReviewingController::class, 'show']);
$app->post('/reviewing/{assignment_id}/submit', [ReviewingController::class, 'submit']);
$app->patch('/reviewing/{assignment_id}', [ReviewingController::class, 'submit']);

$app->get('/rebuttal', [RebuttalController::class, 'index']);
$app->post('/rebuttal/submit', [RebuttalController::class, 'submit']);

$app->get('/decision_management', [DecisionManagementController::class, 'index']);
$app->post('/decision_management/record', [DecisionManagementController::class, 'record']);

$app->map(['GET', 'POST'], '/double_blind_views/{submission_id}', [DoubleBlindViewsController::class, 'index']);

$app->get('/bulk_exports', [BulkExportsController::class, 'index']);
$app->post('/bulk_exports/run', [BulkExportsController::class, 'export']);
$app->get('/bulk_exports/{id}/download', [BulkExportsController::class, 'download']);

$app->get('/frontend_api_integration_and_errors', [FrontendApiController::class, 'index']);
$app->post('/frontend_api_integration_and_errors/report', [FrontendApiController::class, 'reportError']);
$app->get('/api/errors/{code}', [FrontendApiController::class, 'apiError']);

$app->map(['GET', 'POST', 'PATCH'], '/api/conf/{use_case}', [ApiRouterController::class, 'dispatch']);
$app->map(['GET', 'POST', 'PATCH'], '/api/conf/{use_case}/{id}', [ApiRouterController::class, 'dispatch']);

$app->addErrorMiddleware(true, true, true);

// Serve static files (CSS/JS) directly from /public/static
$app->get('/static/{file:.+}', function ($req, $resp, $args) {
    $file = dirname(__DIR__) . '/public/static/' . $args['file'];
    if (!file_exists($file)) {
        return $resp->withStatus(404);
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    if ($ext === 'css') $mime = 'text/css';
    elseif ($ext === 'js') $mime = 'application/javascript';
    elseif ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'svg') $mime = 'image/svg+xml';
    $resp->getBody()->write(file_get_contents($file));
    return $resp->withHeader('Content-Type', $mime);
});

$app->run();