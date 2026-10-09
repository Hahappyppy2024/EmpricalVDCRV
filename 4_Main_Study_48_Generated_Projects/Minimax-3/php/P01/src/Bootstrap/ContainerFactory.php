<?php
declare(strict_types=1);

namespace LMS\Bootstrap;

use DI\Container;

/**
 * Bootstrap a PHP-DI container with config + core services.
 */
final class ContainerFactory
{
    public static function build(array $config): Container
    {
        $container = new Container();
        $container->set('config', $config);
        $container->set(\PDO::class, function () use ($config) {
            Database::init($config['db']['path']);
            return Database::pdo();
        });
        $container->set(\LMS\Http\View::class, function ($c) {
            return new \LMS\Http\View($c->get('config'));
        });
        $container->set(\LMS\Http\Session::class, function ($c) {
            return new \LMS\Http\Session($c->get('config'));
        });
        $container->set(\LMS\Http\Csrf::class, function ($c) {
            return new \LMS\Http\Csrf($c->get(\LMS\Http\Session::class));
        });
        $container->set(\LMS\Auth\AuthService::class, function ($c) {
            return new \LMS\Auth\AuthService(
                $c->get(\PDO::class),
                $c->get(\LMS\Http\Session::class),
                $c->get('config')
            );
        });
        $container->set(\LMS\Service\ExportService::class, function ($c) {
            return new \LMS\Service\ExportService(
                $c->get(\PDO::class),
                $c->get('config')
            );
        });

        // Repositories
        $container->set(\LMS\Repository\UserRepository::class, fn($c) => new \LMS\Repository\UserRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\CourseRepository::class, fn($c) => new \LMS\Repository\CourseRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\EnrollmentRepository::class, fn($c) => new \LMS\Repository\EnrollmentRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\MaterialRepository::class, fn($c) => new \LMS\Repository\MaterialRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\AnnouncementRepository::class, fn($c) => new \LMS\Repository\AnnouncementRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\DiscussionRepository::class, fn($c) => new \LMS\Repository\DiscussionRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\AssignmentRepository::class, fn($c) => new \LMS\Repository\AssignmentRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\QuizRepository::class, fn($c) => new \LMS\Repository\QuizRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\GradeRepository::class, fn($c) => new \LMS\Repository\GradeRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\ExportRepository::class, fn($c) => new \LMS\Repository\ExportRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\ReportRepository::class, fn($c) => new \LMS\Repository\ReportRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\AuditRepository::class, fn($c) => new \LMS\Repository\AuditRepository($c->get(\PDO::class)));
        $container->set(\LMS\Repository\SettingsRepository::class, fn($c) => new \LMS\Repository\SettingsRepository($c->get(\PDO::class)));

        // Controllers that need raw $config
        $container->set(\LMS\Controller\CourseMaterialController::class, function ($c) {
            return new \LMS\Controller\CourseMaterialController(
                $c->get(\LMS\Http\View::class),
                $c->get(\LMS\Repository\CourseRepository::class),
                $c->get(\LMS\Repository\MaterialRepository::class),
                $c->get(\LMS\Repository\EnrollmentRepository::class),
                $c->get(\LMS\Auth\AuthService::class),
                $c->get(\LMS\Http\Session::class),
                $c->get(\LMS\Http\Csrf::class),
                $c->get('config')
            );
        });
        $container->set(\LMS\Controller\AssignmentSubmissionController::class, function ($c) {
            return new \LMS\Controller\AssignmentSubmissionController(
                $c->get(\LMS\Http\View::class),
                $c->get(\LMS\Repository\AssignmentRepository::class),
                $c->get(\LMS\Repository\CourseRepository::class),
                $c->get(\LMS\Repository\EnrollmentRepository::class),
                $c->get(\LMS\Repository\GradeRepository::class),
                $c->get(\LMS\Auth\AuthService::class),
                $c->get(\LMS\Http\Session::class),
                $c->get(\LMS\Http\Csrf::class),
                $c->get('config')
            );
        });

        return $container;
    }
}
