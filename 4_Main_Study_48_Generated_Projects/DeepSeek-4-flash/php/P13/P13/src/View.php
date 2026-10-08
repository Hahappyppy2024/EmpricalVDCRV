<?php

declare(strict_types=1);

namespace P13;

/**
 * Minimal server-side template renderer.
 */
final class View
{
    private string $dir;

    public function __construct(private Config $config)
    {
        $this->dir = base_path('templates');
    }

    /**
     * Render a template wrapped in the shared layout.
     */
    public function render(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        $tplFile = $this->dir . DIRECTORY_SEPARATOR . $template . '.php';

        if (!is_file($tplFile)) {
            return $this->errorHtml("Template not found: " . e($template));
        }

        ob_start();
        include $tplFile;
        $content = ob_get_clean();

        $title = $data['title'] ?? 'P13 Mail Server / Admin Console';
        $user = $data['user'] ?? null;
        $active = $data['active'] ?? '';
        $csrf = $data['csrf'] ?? ($user !== null ? '' : '');
        $wsUrl = (string) $this->config->get('ws.url', 'ws://127.0.0.1:8090');

        ob_start();
        include $this->dir . DIRECTORY_SEPARATOR . 'layout.php';
        return (string) ob_get_clean();
    }

    private function errorHtml(string $message): string
    {
        return '<html><body><h1>Template error</h1><p>' . $message . '</p></body></html>';
    }
}
