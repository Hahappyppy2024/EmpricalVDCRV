<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Middleware\AuthMiddleware;
use App\Middleware\ErrorMiddleware;
use App\Middleware\RequireRole;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GradeRepository;
use App\Repositories\QuizRepository;
use App\Repositories\SystemRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CapabilityService;
use App\Services\ExportService;
use App\Services\FileService;
use App\Services\RealtimeService;
use App\Services\Validator;
use Slim\Factory\AppFactory;

/**
 * Builds the Slim application: container wiring, middleware, and routes.
 */
final class App
{
    public static function create(Config $config): \Slim\App
    {
        $container = new Container();
        $container->instance(Config::class, $config);
        $container->set(\Slim\Psr7\Factory\ResponseFactory::class, static fn () => new \Slim\Psr7\Factory\ResponseFactory());
        $container->set(Database::class, static fn (Container $c) => new Database($c->get(Config::class)));
        $container->set(AuthService::class, static fn (Container $c) => new AuthService($c->get(Database::class), $c->get(Config::class)));
        $container->set(CapabilityService::class, static fn (Container $c) => new CapabilityService($c->get(Database::class)));
        $container->set(Validator::class, static fn () => new Validator());
        $container->set(FileService::class, static fn (Container $c) => new FileService($c->get(Config::class)));
        $container->set(ExportService::class, static fn (Container $c) => new ExportService($c->get(Database::class), $c->get(Config::class)));
        $container->set(RealtimeService::class, static fn (Container $c) => new RealtimeService($c->get(Database::class)));

        $container->set(UserRepository::class, static fn (Container $c) => new UserRepository($c->get(Database::class)));
        $container->set(CourseRepository::class, static fn (Container $c) => new CourseRepository($c->get(Database::class)));
        $container->set(AssignmentRepository::class, static fn (Container $c) => new AssignmentRepository($c->get(Database::class)));
        $container->set(QuizRepository::class, static fn (Container $c) => new QuizRepository($c->get(Database::class)));
        $container->set(GradeRepository::class, static fn (Container $c) => new GradeRepository($c->get(Database::class)));
        $container->set(SystemRepository::class, static fn (Container $c) => new SystemRepository($c->get(Database::class)));

        $container->set(AuthController::class, static fn (Container $c) => new AuthController(
            $c->get(Config::class), $c->get(AuthService::class), $c->get(Validator::class), $c->get(SystemRepository::class)
        ));
        $container->set(PageController::class, static fn (Container $c) => new PageController(
            $c->get(Config::class),
            $c->get(AuthService::class),
            $c->get(CapabilityService::class),
            $c->get(CourseRepository::class),
            $c->get(AssignmentRepository::class),
            $c->get(QuizRepository::class),
            $c->get(GradeRepository::class),
            $c->get(SystemRepository::class),
            $c->get(UserRepository::class),
            $c->get(FileService::class),
            $c->get(ExportService::class)
        ));
        $container->set(ApiController::class, static fn (Container $c) => new ApiController(
            $c->get(Config::class),
            $c->get(Database::class),
            $c->get(AuthService::class),
            $c->get(CapabilityService::class),
            $c->get(Validator::class),
            $c->get(UserRepository::class),
            $c->get(CourseRepository::class),
            $c->get(AssignmentRepository::class),
            $c->get(QuizRepository::class),
            $c->get(GradeRepository::class),
            $c->get(SystemRepository::class),
            $c->get(FileService::class),
            $c->get(ExportService::class),
            $c->get(RealtimeService::class)
        ));

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new AuthMiddleware($container->get(AuthService::class)));
        $app->add(new ErrorMiddleware($container->get(\Slim\Psr7\Factory\ResponseFactory::class)));

        self::registerRoutes($app, $container);

        return $app;
    }

    private static function registerRoutes(\Slim\App $app, Container $container): void
    {
        $auth = AuthController::class;
        $page = PageController::class;
        $api = ApiController::class;

        // ---- Account access (LMS-01) ----
        $app->get('/login', [$auth, 'loginForm']);
        $app->post('/login', [$auth, 'login']);
        $app->get('/register', [$auth, 'registerForm']);
        $app->post('/register', [$auth, 'register']);
        $app->get('/forgot-password', [$auth, 'forgotForm']);
        $app->post('/forgot-password', [$auth, 'forgot']);
        $app->get('/reset-password', [$auth, 'resetForm']);
        $app->post('/reset-password', [$auth, 'reset']);
        $app->get('/logout', [$auth, 'logout'])->add(new RequireRole(['student', 'instructor', 'admin', 'visitor'], $container->get(\Slim\Psr7\Factory\ResponseFactory::class)));
        $app->post('/logout', [$auth, 'logout'])->add(new RequireRole(['student', 'instructor', 'admin', 'visitor'], $container->get(\Slim\Psr7\Factory\ResponseFactory::class)));

        // ---- Dashboard / course discovery (LMS-02) ----
        $app->get('/dashboard', [$page, 'dashboard']);
        $app->get('/courses', [$page, 'courses']);
        $app->get('/courses/{id}', [$page, 'courseDetail']);
        $app->get('/courses/{id}/discussion', [$page, 'courseDiscussion']);
        $app->get('/courses/{id}/assignments', [$page, 'courseAssignments']);
        $app->get('/courses/{id}/quizzes', [$page, 'courseQuizzes']);
        $app->get('/courses/{id}/gradebook', [$page, 'courseGradebook'])->add(new RequireRole(['instructor', 'admin'], $container->get(\Slim\Psr7\Factory\ResponseFactory::class)));

        // ---- Quizzes (LMS-08) ----
        $app->get('/quizzes/{id}/take', [$page, 'quizTake']);
        $app->get('/quizzes/{id}/results', [$page, 'quizResults']);

        // ---- Grades (LMS-09) / export (LMS-10) ----
        $app->get('/grades', [$page, 'myGrades']);
        $app->get('/exports', [$page, 'exports']);

        // ---- Admin (LMS-11, LMS-12) ----
        $adminMw = new RequireRole(['admin'], $container->get(\Slim\Psr7\Factory\ResponseFactory::class));
        $app->get('/admin', [$page, 'adminDashboard'])->add($adminMw);
        $app->get('/admin/reports', [$page, 'adminReports'])->add($adminMw);

        // ---- Frontend API integration (LMS-13) / error responses (LMS-14) ----
        $app->get('/api-integration', [$page, 'apiIntegration']);
        $app->get('/error-demo', [$page, 'errorDemo']);

        // ---- Downloads ----
        $app->get('/download/material/{id}', [$page, 'downloadMaterial']);
        $app->get('/download/submission/{id}', [$page, 'downloadSubmission']);
        $app->get('/download/export/{id}', [$page, 'downloadExport'])->add(new RequireRole(['instructor', 'admin'], $container->get(\Slim\Psr7\Factory\ResponseFactory::class)));

        // ---- JSON API (all LMS modules) ----
        $app->get('/api/me', [$api, 'me']);
        $app->get('/api/lms/{module}', [$api, 'list']);
        $app->post('/api/lms/{module}', [$api, 'create']);
        $app->patch('/api/lms/{module}/{id}', [$api, 'update']);
    }
}
