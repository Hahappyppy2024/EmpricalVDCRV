<?php

declare(strict_types=1);

namespace App;

final class View
{
    public function __construct(private readonly string $templateDir)
    {
    }

    /**
     * Render a template using an optional layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        $data['view'] = $this;
        $data['content'] = $this->renderPartial($template, $data);

        if ($layout === '') {
            return (string) $data['content'];
        }

        return $this->renderPartial($layout, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new NotFoundException('Template not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
