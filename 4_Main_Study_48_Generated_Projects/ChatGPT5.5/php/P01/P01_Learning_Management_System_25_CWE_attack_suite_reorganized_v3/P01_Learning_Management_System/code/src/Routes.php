<?php
declare(strict_types=1);

namespace App;

use PDO;
use Slim\App;

final class Routes
{
    public static function register(App $app, PDO $db, Auth $auth, array $config): void
    {
        CoreRoutes::register($app, $db, $auth, $config);
        CourseRoutes::register($app, $db, $auth, $config);
        AssessmentRoutes::register($app, $db, $auth, $config);
    }
}
