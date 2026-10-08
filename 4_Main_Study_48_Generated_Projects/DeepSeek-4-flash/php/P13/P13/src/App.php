<?php

declare(strict_types=1);

namespace P13;

use P13\Middleware\CsrfMiddleware;
use P13\Middleware\SessionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

/**
 * Build and configure the Slim application.
 */
final class App
{
    public static function create(): \Slim\App
    {
        require_once __DIR__ . '/helpers.php';

        $config = new Config();
        $container = new AppContainer();
        $container->set(Config::class, static fn () => $config);
        $container->set(Database::class, static fn () => new Database($config));
        $container->set(Validation::class, static fn () => new Validation());
        $container->set(Session::class, static fn ($c) => new Session($c->get(Database::class), $config));
        $container->set(Auth::class, static fn ($c) => new Auth($c->get(Database::class), $c->get(Session::class), $config));
        $container->set(Audit::class, static fn ($c) => new Audit($c->get(Database::class)));
        $container->set(Storage::class, static fn ($c) => new Storage($c->get(Database::class), $config));
        $container->set(View::class, static fn () => new View($config));

        // Ensure the schema exists (idempotent) on every boot.
        $db = $container->get(Database::class);
        (new Schema())->migrate($db->pdo());

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware((bool) $config->get('app.debug'), false, false);
        $errorMiddleware->setDefaultErrorHandler(new ErrorHandler((bool) $config->get('app.debug')));

        $session = $container->get(Session::class);
        $app->add(new CsrfMiddleware($session));
        $app->add(new SessionMiddleware($session));
        $app->addRoutingMiddleware();

        Routes::register($app);

        return $app;
    }
}
