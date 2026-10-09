<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class View
{
    public static function render(ResponseInterface $response, string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $templates = dirname(__DIR__) . '/src/Views';
        $template = ltrim($template, '/');
        $file = $templates . '/' . $template . '.php';
        if (!file_exists($file)) {
            $resp = new Response(500);
            $resp->getBody()->write('View not found: ' . htmlspecialchars($template));
            return $resp;
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();
        $layoutFile = $templates . '/layout.php';
        if ($template !== 'layout' && file_exists($layoutFile)) {
            ob_start();
            include $layoutFile;
            $page = ob_get_clean();
        } else {
            $page = $content;
        }
        $resp = $response->withStatus($status);
        $resp->getBody()->write($page);
        return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $resp = $response->withStatus($status);
        $resp->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $resp->withHeader('Content-Type', 'application/json');
    }

    public static function redirect(ResponseInterface $response, string $location, int $status = 302): ResponseInterface
    {
        return $response->withStatus($status)->withHeader('Location', $location);
    }
}