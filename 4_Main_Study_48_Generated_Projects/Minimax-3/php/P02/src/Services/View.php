<?php
declare(strict_types=1);

namespace App\Services;

final class View
{
    public static function render(string $template, array $data = [], array $config = []): string
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        $user = SessionService::user();
        $csrf = SessionService::csrfToken();
        $appName = $config['app']['name'] ?? 'Conference';
        ob_start();
        include $templatePath;
        return (string)ob_get_clean();
    }

    public static function layout(string $title, string $body, array $config, ?array $user): string
    {
        ob_start();
        $appName = $config['app']['name'] ?? 'Conference';
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        include dirname(__DIR__, 2) . '/templates/layout.php';
        return (string)ob_get_clean();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function json($data, int $status = 200): \Psr\Http\Message\ResponseInterface
    {
        $response = new \Slim\Psr7\Response($status);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function html(string $body, int $status = 200): \Psr\Http\Message\ResponseInterface
    {
        $response = new \Slim\Psr7\Response($status);
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function redirect(string $url, int $status = 302): \Psr\Http\Message\ResponseInterface
    {
        $response = new \Slim\Psr7\Response($status);
        return $response->withHeader('Location', $url);
    }
}