<?php

declare(strict_types=1);

namespace Shop\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Minimal PSR-3 logger that appends to a local file (deterministic, offline).
 */
final class FileLogger extends AbstractLogger
{
    public function __construct(private string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $label = strtoupper((string) $level);
        $extra = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        @file_put_contents(
            $this->path,
            date('c') . " [{$label}] {$message}{$extra}\n",
            FILE_APPEND
        );
    }
}
