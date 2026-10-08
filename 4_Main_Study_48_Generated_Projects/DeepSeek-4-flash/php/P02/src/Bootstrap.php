<?php

declare(strict_types=1);

namespace App;

use App\Config\Config;
use App\Container\AppContainer;
use App\Middleware\JsonErrorHandler;
use App\Services\Workflows\AccountAccessRecoveryService;
use App\Services\Workflows\BulkExportsService;
use App\Services\Workflows\ConferencePhasesService;
use App\Services\Workflows\DecisionManagementService;
use App\Services\Workflows\DoubleBlindViewsService;
use App\Services\Workflows\FrontendApiService;
use App\Services\Workflows\ManuscriptAccessService;
use App\Services\Workflows\PaperSubmissionService;
use App\Services\Workflows\RebuttalService;
use App\Services\Workflows\ReviewerAssignmentService;
use App\Services\Workflows\ReviewingService;
use App\Services\Workflows\SubmissionDiscoveryService;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

final class Bootstrap
{
    public static function create(string $rootDir): \Slim\App
    {
        $rootDir = realpath($rootDir) ?: $rootDir;
        $config = Config::load($rootDir);
        $container = self::buildContainer($config);

        AppFactory::setContainer($container);
        AppFactory::setResponseFactory(new ResponseFactory());
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware($config['display_errors'], true, true);
        $errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

        self::registerRoutes($app, $container, $config);

        return $app;
    }

    private static function buildContainer(array $config): AppContainer
    {
        $container = new AppContainer(['config' => $config]);

        $container->set(PDO::class, static fn (): PDO => Repositories\Database::connect($config['db_path']));
        $container->set(ResponseFactoryInterface::class, static fn () => new ResponseFactory());

        // Repositories
        $repos = [
            'UserRepository', 'SessionRepository', 'AccountAccessRepository', 'ConferencePhasesRepository',
            'PaperSubmissionRepository', 'StoredFileRepository', 'SubmissionDiscoveryRepository',
            'ManuscriptAccessRepository', 'ReviewerAssignmentRepository', 'ReviewRepository', 'RebuttalRepository',
            'DecisionRepository', 'AuditEventRepository', 'DoubleBlindViewsRepository', 'BulkExportRepository',
            'FrontendApiRepository', 'RealtimeEventRepository',
        ];
        foreach ($repos as $repo) {
            $class = 'App\\Repositories\\' . $repo;
            $container->set($class, static fn (ContainerInterface $c): mixed => new $class($c->get(PDO::class)));
        }

        // Services
        $container->set(Services\SessionService::class, static fn (ContainerInterface $c) => new Services\SessionService(
            $c->get(Repositories\SessionRepository::class),
            $c->get(Repositories\UserRepository::class),
            (int) $config['session_lifetime']
        ));
        $container->set(Services\MailService::class, static fn () => new Services\MailService($config['mail_log']));
        $container->set(Services\AuthService::class, static fn (ContainerInterface $c) => new Services\AuthService(
            $c->get(Repositories\UserRepository::class),
            $c->get(Repositories\SessionRepository::class),
            $c->get(Repositories\AccountAccessRepository::class),
            $c->get(Services\SessionService::class),
            $c->get(Services\MailService::class)
        ));
        $container->set(Services\PhaseService::class, static fn (ContainerInterface $c) => new Services\PhaseService(
            $c->get(Repositories\ConferencePhasesRepository::class)
        ));
        $container->set(Services\AuditService::class, static fn (ContainerInterface $c) => new Services\AuditService(
            $c->get(Repositories\AuditEventRepository::class)
        ));
        $container->set(Services\FileService::class, static fn (ContainerInterface $c) => new Services\FileService(
            $c->get(Repositories\StoredFileRepository::class),
            $config['storage_path']
        ));
        $container->set(Services\PdfService::class, static fn () => new Services\PdfService());
        $container->set(Services\ExportService::class, static fn (ContainerInterface $c) => new Services\ExportService(
            $c->get(Repositories\PaperSubmissionRepository::class),
            $c->get(Repositories\ReviewRepository::class),
            $c->get(Repositories\DecisionRepository::class),
            $c->get(Repositories\BulkExportRepository::class),
            $c->get(Services\FileService::class),
            $c->get(Services\PdfService::class)
        ));
        $container->set(Services\RealtimeService::class, static fn (ContainerInterface $c) => new Services\RealtimeService(
            $c->get(Repositories\RealtimeEventRepository::class)
        ));

        // Workflow services
        $workflowServices = [
            AccountAccessRecoveryService::class => static fn (ContainerInterface $c) => new AccountAccessRecoveryService(
                $c->get(Repositories\AccountAccessRepository::class),
                $c->get(Repositories\UserRepository::class),
                $c->get(Services\AuthService::class)
            ),
            ConferencePhasesService::class => static fn (ContainerInterface $c) => new ConferencePhasesService(
                $c->get(Repositories\ConferencePhasesRepository::class),
                $c->get(Services\RealtimeService::class)
            ),
            PaperSubmissionService::class => static fn (ContainerInterface $c) => new PaperSubmissionService(
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Repositories\StoredFileRepository::class),
                $c->get(Services\FileService::class),
                $c->get(Services\PhaseService::class),
                $c->get(Services\RealtimeService::class)
            ),
            SubmissionDiscoveryService::class => static fn (ContainerInterface $c) => new SubmissionDiscoveryService(
                $c->get(Repositories\SubmissionDiscoveryRepository::class),
                $c->get(PaperSubmissionService::class)
            ),
            ManuscriptAccessService::class => static fn (ContainerInterface $c) => new ManuscriptAccessService(
                $c->get(Repositories\ManuscriptAccessRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Repositories\StoredFileRepository::class),
                $c->get(Repositories\ReviewerAssignmentRepository::class)
            ),
            ReviewerAssignmentService::class => static fn (ContainerInterface $c) => new ReviewerAssignmentService(
                $c->get(Repositories\ReviewerAssignmentRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Repositories\UserRepository::class),
                $c->get(Services\AuditService::class),
                $c->get(Services\RealtimeService::class)
            ),
            ReviewingService::class => static fn (ContainerInterface $c) => new ReviewingService(
                $c->get(Repositories\ReviewRepository::class),
                $c->get(Repositories\ReviewerAssignmentRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Services\PhaseService::class),
                $c->get(Services\RealtimeService::class)
            ),
            RebuttalService::class => static fn (ContainerInterface $c) => new RebuttalService(
                $c->get(Repositories\RebuttalRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Repositories\ReviewerAssignmentRepository::class),
                $c->get(Services\PhaseService::class),
                $c->get(Services\RealtimeService::class)
            ),
            DecisionManagementService::class => static fn (ContainerInterface $c) => new DecisionManagementService(
                $c->get(Repositories\DecisionRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Services\AuditService::class),
                $c->get(Services\MailService::class),
                $c->get(Services\PhaseService::class),
                $c->get(Services\RealtimeService::class)
            ),
            DoubleBlindViewsService::class => static fn (ContainerInterface $c) => new DoubleBlindViewsService(
                $c->get(Repositories\DoubleBlindViewsRepository::class),
                $c->get(Repositories\PaperSubmissionRepository::class),
                $c->get(Repositories\ReviewRepository::class),
                $c->get(Repositories\ReviewerAssignmentRepository::class)
            ),
            BulkExportsService::class => static fn (ContainerInterface $c) => new BulkExportsService(
                $c->get(Repositories\BulkExportRepository::class),
                $c->get(Services\ExportService::class),
                $c->get(Services\RealtimeService::class)
            ),
            FrontendApiService::class => static fn (ContainerInterface $c) => new FrontendApiService(
                $c->get(Repositories\FrontendApiRepository::class)
            ),
            \App\Services\Workflows\RealtimeEventsService::class => static fn (ContainerInterface $c) => new \App\Services\Workflows\RealtimeEventsService(
                $c->get(Services\RealtimeService::class)
            ),
        ];
        foreach ($workflowServices as $class => $factory) {
            $container->set($class, $factory);
        }

        // Controllers
        $container->set(Controllers\AuthController::class, static fn (ContainerInterface $c) => new Controllers\AuthController(
            $c->get(Services\AuthService::class),
            $c->get(Services\SessionService::class),
            $c->get(ResponseFactoryInterface::class),
            $config
        ));
        $container->set(Controllers\HealthController::class, static fn (ContainerInterface $c) => new Controllers\HealthController(
            $c->get(PDO::class)
        ));
        $container->set(Controllers\WorkflowController::class, static fn (ContainerInterface $c) => new Controllers\WorkflowController(
            $c,
            require $config['root'] . '/src/Routes/workflows.php'
        ));
        $container->set(Controllers\PageController::class, static fn (ContainerInterface $c) => new Controllers\PageController(
            new Views\ViewRenderer($config['root'] . '/src/Views'),
            $c->get(Services\PhaseService::class),
            $c->get(PaperSubmissionService::class),
            $c->get(SubmissionDiscoveryService::class),
            $c->get(ManuscriptAccessService::class),
            $c->get(ReviewerAssignmentService::class),
            $c->get(ReviewingService::class),
            $c->get(RebuttalService::class),
            $c->get(DecisionManagementService::class),
            $c->get(ConferencePhasesService::class),
            $c->get(BulkExportsService::class),
            $c->get(AccountAccessRecoveryService::class),
            $c->get(DoubleBlindViewsService::class),
            $c->get(FrontendApiService::class),
            $c->get(Services\AuditService::class),
            $c->get(PDO::class),
            $config
        ));

        // Middleware
        $container->set(Middleware\AuthMiddleware::class, static fn (ContainerInterface $c) => new Middleware\AuthMiddleware(
            $c->get(Services\SessionService::class),
            $c->get(ResponseFactoryInterface::class),
            (string) $config['session_name']
        ));
        $container->set('chair_role', static fn (ContainerInterface $c) => new Middleware\RoleMiddleware(
            ['chair', 'admin'],
            $c->get(ResponseFactoryInterface::class)
        ));
        $container->set('admin_role', static fn (ContainerInterface $c) => new Middleware\RoleMiddleware(
            ['admin'],
            $c->get(ResponseFactoryInterface::class)
        ));
        $container->set('author_role', static fn (ContainerInterface $c) => new Middleware\RoleMiddleware(
            ['author', 'chair', 'admin'],
            $c->get(ResponseFactoryInterface::class)
        ));
        $container->set('reviewer_role', static fn (ContainerInterface $c) => new Middleware\RoleMiddleware(
            ['reviewer', 'chair', 'admin'],
            $c->get(ResponseFactoryInterface::class)
        ));
        $container->set(JsonErrorHandler::class, static fn (ContainerInterface $c) => new JsonErrorHandler(
            $c->get(ResponseFactoryInterface::class),
            (string) $config['error_log']
        ));

        return $container;
    }

    private static function registerRoutes(\Slim\App $app, ContainerInterface $container, array $config): void
    {
        (require $config['root'] . '/src/Routes/routes.php')($app, $container);
    }
}
