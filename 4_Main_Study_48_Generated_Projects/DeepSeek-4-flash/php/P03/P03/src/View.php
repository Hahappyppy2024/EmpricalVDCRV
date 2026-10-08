<?php

declare(strict_types=1);

namespace Shop;

final class View
{
    /** @var array<string,mixed> */
    private array $globals;

    /**
     * @param array<string,mixed> $globals
     */
    public function __construct(private string $dir, array $globals = [])
    {
        $this->globals = $globals;
    }

    public function put(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    public function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        extract($this->globals, EXTR_SKIP);
        extract($data, EXTR_SKIP);
        ob_start();
        include $this->dir . '/' . $template . '.php';
        $content = (string) ob_get_clean();
        $page = $template;
        ob_start();
        include $this->dir . '/' . $layout . '.php';
        return (string) ob_get_clean();
    }
}
