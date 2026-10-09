<?php
declare(strict_types=1);
namespace App\Infrastructure;

use Psr\Http\Message\ResponseInterface;

final class View
{
    public static function render(ResponseInterface $response, string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $body = self::fetch($template, $data);
        $response->getBody()->write($body);
        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function fetch(string $template, array $data = []): string
    {
        $tplDir = __DIR__ . '/../Templates/';
        $layout = $tplDir . 'layout.php';
        $file = $tplDir . $template . '.php';
        if (!is_file($file)) {
            return 'missing template';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = (string)ob_get_clean();
        ob_start();
        include $layout;
        return (string)ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}