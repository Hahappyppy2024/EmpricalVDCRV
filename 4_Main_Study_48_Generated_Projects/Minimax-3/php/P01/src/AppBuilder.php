<?php
declare(strict_types=1);

namespace LMS;

use DI\Container;
use LMS\Auth\AuthService;
use LMS\Auth\HttpException;
use LMS\Bootstrap\ContainerFactory;
use LMS\Bootstrap\Database;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Middleware\AuthMiddleware;
use LMS\Middleware\CsrfMiddleware;
use LMS\Repository\AuditRepository;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Throwable;

/**
 * Wires the Slim 4 application, middleware, error handlers and routes.
 */
final class AppBuilder
{
    public static function build(array $config): App
    {
        $container = ContainerFactory::build($config);
        Database::init($config['db']['path']);
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new CsrfMiddleware($container->get(Csrf::class)));

        // Error handlers
        $errorMiddleware = $app->addErrorMiddleware($config['app']['debug'], true, true);

        $errorMiddleware->setDefaultErrorHandler(function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails
        ) use ($container) {
            $status = $exception instanceof HttpException ? $exception->statusCode() : 500;
            $payload = [
                'error' => $status >= 500 ? 'Internal server error.' : $exception->getMessage(),
                'status' => $status,
            ];
            if ($displayErrorDetails) {
                $payload['debug'] = [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile() . ':' . $exception->getLine(),
                ];
            }
            if (str_starts_with($request->getUri()->getPath(), '/api/')
                || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                $response = new Response($status);
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json');
            }
            $view = $container->get(View::class);
            $user = null;
            try {
                $user = $container->get(AuthService::class)->currentUser();
            } catch (Throwable) {
            }
            return $view->render(new Response($status), 'error.php', [
                'user' => $user,
                'error' => $payload['error'],
                'status' => $status,
            ]);
        });

        self::routes($app);
        return $app;
    }

    private static function routes(App $app): void
    {
        $container = $app->getContainer();
        $authMw = AuthMiddleware::class;

        // Home / dashboard routing
        $app->get('/', \LMS\Controller\HomeController::class . ':index');

        // LMS-01 — Account access
        $app->map(['GET', 'POST'], '/login',          \LMS\Controller\AccountAccessController::class . ':login');
        $app->map(['GET', 'POST'], '/register',       \LMS\Controller\AccountAccessController::class . ':register');
        $app->get( '/logout',                         \LMS\Controller\AccountAccessController::class . ':logout');
        $app->map(['GET', 'POST'], '/forgot',         \LMS\Controller\AccountAccessController::class . ':forgot');
        $app->map(['GET', 'POST'], '/reset/{token}',  \LMS\Controller\AccountAccessController::class . ':reset');
        $app->get( '/dashboard',                     \LMS\Controller\AccountAccessController::class . ':dashboard')
            ->add(new $authMw($container->get(AuthService::class)));

        // API aliases (also under /api/lms/...) matching the spec paths
        $api = function (string $name) use ($app, $container, $authMw) {
            $path = '/api/lms/' . $name;
            $app->map(['GET','POST'], $path, \LMS\Controller\ApiController::class . ':' . $name)
                ->add(new $authMw($container->get(AuthService::class)));
            $app->map(['GET','PATCH','DELETE'], $path . '/{id}', \LMS\Controller\ApiController::class . ':' . $name . 'Item')
                ->add(new $authMw($container->get(AuthService::class)));
        };
        foreach ([
            'account_access','course_discovery','enrollment','course_materials',
            'announcements','discussion_board','assignment_submission','quiz_lifecycle',
            'grades','grade_export','bulk_course_report','administrative_api',
            'frontend_api_integration','error_responses'
        ] as $slug) {
            $api($slug);
        }

        // LMS-02 — Course discovery (HTML)
        $app->get('/courses', \LMS\Controller\CourseDiscoveryController::class . ':index');
        $app->get('/courses/{id:\d+}', \LMS\Controller\CourseDiscoveryController::class . ':show');

        // LMS-03 — Enrollment
        $app->map(['GET','POST'], '/enroll/{id:\d+}', \LMS\Controller\EnrollmentController::class . ':enroll')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->post('/enroll/{id:\d+}/drop', \LMS\Controller\EnrollmentController::class . ':drop')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->get('/my/courses', \LMS\Controller\EnrollmentController::class . ':mine')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->get('/courses/{id:\d+}/roster', \LMS\Controller\EnrollmentController::class . ':roster')
            ->add(new $authMw($container->get(AuthService::class), 'instructor', 'admin'));

        // LMS-04 — Course materials
        $app->get('/courses/{id:\d+}/materials', \LMS\Controller\CourseMaterialController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/materials/new', \LMS\Controller\CourseMaterialController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->get('/materials/{id:\d+}/download', \LMS\Controller\CourseMaterialController::class . ':download')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->post('/materials/{id:\d+}/delete', \LMS\Controller\CourseMaterialController::class . ':delete')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));

        // LMS-05 — Announcements
        $app->get('/courses/{id:\d+}/announcements', \LMS\Controller\AnnouncementController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/announcements/new', \LMS\Controller\AnnouncementController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->post('/announcements/{id:\d+}/delete', \LMS\Controller\AnnouncementController::class . ':delete')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));

        // LMS-06 — Discussion board
        $app->get('/courses/{id:\d+}/discussion', \LMS\Controller\DiscussionBoardController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/discussion/new', \LMS\Controller\DiscussionBoardController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/discussion/{id:\d+}/reply', \LMS\Controller\DiscussionBoardController::class . ':reply')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/discussion/{id:\d+}/edit', \LMS\Controller\DiscussionBoardController::class . ':edit')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->post('/discussion/{id:\d+}/delete', \LMS\Controller\DiscussionBoardController::class . ':delete')
            ->add(new $authMw($container->get(AuthService::class)));

        // LMS-07 — Assignment submission
        $app->get('/courses/{id:\d+}/assignments', \LMS\Controller\AssignmentSubmissionController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/assignments/new', \LMS\Controller\AssignmentSubmissionController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->get('/assignments/{id:\d+}/submit', \LMS\Controller\AssignmentSubmissionController::class . ':submitForm')
            ->add(new $authMw($container->get(AuthService::class), 'student'));
        $app->post('/assignments/{id:\d+}/submit', \LMS\Controller\AssignmentSubmissionController::class . ':submit')
            ->add(new $authMw($container->get(AuthService::class), 'student'));
        $app->get('/assignments/{id:\d+}/submissions', \LMS\Controller\AssignmentSubmissionController::class . ':submissions')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->post('/submissions/{id:\d+}/grade', \LMS\Controller\AssignmentSubmissionController::class . ':grade')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));

        // LMS-08 — Quiz lifecycle
        $app->get('/courses/{id:\d+}/quizzes', \LMS\Controller\QuizLifecycleController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/quizzes/new', \LMS\Controller\QuizLifecycleController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->map(['GET','POST'], '/quizzes/{id:\d+}/take', \LMS\Controller\QuizLifecycleController::class . ':take')
            ->add(new $authMw($container->get(AuthService::class), 'student'));
        $app->get('/quizzes/{id:\d+}/attempts', \LMS\Controller\QuizLifecycleController::class . ':attempts')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));

        // LMS-09 — Grades
        $app->get('/grades', \LMS\Controller\GradesController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));
        $app->map(['GET','POST'], '/courses/{id:\d+}/grades/new', \LMS\Controller\GradesController::class . ':create')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));
        $app->post('/grades/{id:\d+}/update', \LMS\Controller\GradesController::class . ':update')
            ->add(new $authMw($container->get(AuthService::class), 'instructor'));

        // LMS-10 — Grade export
        $app->map(['GET','POST'], '/courses/{id:\d+}/export', \LMS\Controller\GradeExportController::class . ':export')
            ->add(new $authMw($container->get(AuthService::class), 'instructor', 'admin'));
        $app->get('/exports/{id:\d+}/download', \LMS\Controller\GradeExportController::class . ':download')
            ->add(new $authMw($container->get(AuthService::class), 'instructor', 'admin'));
        $app->get('/exports', \LMS\Controller\GradeExportController::class . ':history')
            ->add(new $authMw($container->get(AuthService::class), 'instructor', 'admin'));

        // LMS-11 — Bulk course report
        $app->map(['GET','POST'], '/admin/reports/bulk', \LMS\Controller\BulkCourseReportController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->get('/reports/{id:\d+}/download', \LMS\Controller\BulkCourseReportController::class . ':download')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));

        // LMS-12 — Administrative API (HTML + actions)
        $app->get('/admin', \LMS\Controller\AdminApiController::class . ':dashboard')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->get('/admin/users', \LMS\Controller\AdminApiController::class . ':users')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->post('/admin/users/{id:\d+}/role', \LMS\Controller\AdminApiController::class . ':setRole')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->post('/admin/users/{id:\d+}/status', \LMS\Controller\AdminApiController::class . ':setStatus')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->get('/admin/courses', \LMS\Controller\AdminApiController::class . ':courses')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->map(['GET','POST'], '/admin/courses/new', \LMS\Controller\AdminApiController::class . ':createCourse')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->post('/admin/courses/{id:\d+}/visibility', \LMS\Controller\AdminApiController::class . ':setVisibility')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->get('/admin/settings', \LMS\Controller\AdminApiController::class . ':settings')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->map(['GET','POST'], '/admin/settings/edit', \LMS\Controller\AdminApiController::class . ':settingsEdit')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));
        $app->get('/admin/audit', \LMS\Controller\AdminApiController::class . ':audit')
            ->add(new $authMw($container->get(AuthService::class), 'admin'));

        // LMS-13 — Frontend API integration (single page that exercises all states)
        $app->get('/frontend-api', \LMS\Controller\FrontendApiController::class . ':index')
            ->add(new $authMw($container->get(AuthService::class)));

        // LMS-14 — Error responses (intentional trigger)
        $app->get('/error-demo/{kind}', \LMS\Controller\ErrorController::class . ':demo');

        // Static assets
        $app->get('/assets/{file:.+}', \LMS\Controller\AssetController::class . ':serve');

        // Health
        $app->get('/healthz', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write(json_encode(['status' => 'ok']));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }
}
