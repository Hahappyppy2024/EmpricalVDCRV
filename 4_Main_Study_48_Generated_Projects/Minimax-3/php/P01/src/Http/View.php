<?php
declare(strict_types=1);

namespace LMS\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Minimal plain-PHP template renderer.
 *
 * Templates may be "page" templates that automatically render inside
 * `layout.php`, or "raw" templates that output complete HTML (prefixed
 * with `raw:` in the template name).
 */
final class View
{
    private array $config;
    private string $templatePath;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->templatePath = dirname(__DIR__, 2) . '/templates';
    }

    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $useLayout = true;
        if (str_starts_with($template, 'raw:')) {
            $template = substr($template, 4);
            $useLayout = false;
        }

        $data['view'] = $this;
        $data['appName'] = $this->config['app']['name'];
        $data['appBase'] = $this->config['app']['base_url'];
        $data['user'] = $data['user'] ?? null;
        $data['flash'] = $data['flash'] ?? [];
        $data['error'] = $data['error'] ?? null;
        $data['notice'] = $data['notice'] ?? null;

        $path = $this->templatePath . '/' . $template;
        if (!is_file($path)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        $renderTemplate = static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__path;
            return (string)ob_get_clean();
        };

        $content = $renderTemplate($path, $data);
        $body = $useLayout
            ? $renderTemplate($this->templatePath . '/layout.php', $data + ['content' => $content])
            : $content;

        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function htmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function e(string $value): string
    {
        return $this->htmlEscape($value);
    }

    public function formatDate(?string $value, string $format = 'Y-m-d H:i'): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }

    public function url(string $path): string
    {
        $base = rtrim($this->config['app']['base_url'], '/');
        return $base . '/' . ltrim($path, '/');
    }

    public function asset(string $path): string
    {
        return $this->url('assets/' . ltrim($path, '/'));
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}
