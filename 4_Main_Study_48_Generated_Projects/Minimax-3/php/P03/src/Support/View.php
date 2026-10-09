<?php
declare(strict_types=1);

namespace Shop\Support;

final class View
{
    /**
     * Render a template with shared data, then wrap it in the layout.
     *
     * Templates may set $page_title and must output their content (via echo,
     * or by assigning to $__content which is automatically wrapped).
     */
    public static function render(string $template, array $data = []): string
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template;
        $layoutPath = dirname(__DIR__, 2) . '/templates/layout.php';
        if (!file_exists($templatePath)) {
            return '<p>Template not found.</p>';
        }
        // Capture variables defined inside the template.
        $captureData = [];
        $output = self::capture($templatePath, $data, $captureData);
        $layoutData = array_merge($data, $captureData, [
            'inner' => $output,
            'app_name' => $data['app_name'] ?? 'P03 E-commerce',
        ]);
        $layoutCapture = [];
        return self::capture($layoutPath, $layoutData, $layoutCapture);
    }

    /** Render template without layout. */
    public static function renderRaw(string $template, array $data = []): string
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template;
        if (!file_exists($templatePath)) {
            return '<p>Template not found.</p>';
        }
        return self::capture($templatePath, $data, $captureData);
    }

    private static function capture(string $path, array $data, array &$capture): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $output = ob_get_clean();
        // Best-effort capture of common template-set variables.
        foreach (['page_title', 'error_message'] as $var) {
            if (isset($$var)) {
                $capture[$var] = $$var;
            }
        }
        return (string)$output;
    }
}