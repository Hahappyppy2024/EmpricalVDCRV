<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;

/**
 * Deterministic local export adapter. Generates CSV (and a CSV-based PDF
 * stand-in) files under the configured export directory and records the
 * export row so the grade export / bulk report use cases persist.
 */
final class ExportService
{
    public function __construct(private Database $db, private Config $config)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    public function csv(array $rows, array $columns): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    public function write(string $content, string $extension, string $kind): array
    {
        $filename = $kind . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        file_put_contents($this->config->exportDir() . DIRECTORY_SEPARATOR . $filename, $content);
        return ['file_path' => $filename, 'filename' => $filename];
    }

    /**
     * Deterministic stand-in "PDF" builder that produces a valid CSV payload
     * wrapped in a minimal header so the file downloads reliably.
     */
    public function pdf(array $rows, array $columns, string $title): string
    {
        $content = "%PDF-1.4\n% P01 LMS deterministic PDF export\n% TITLE: {$title}\n";
        foreach ([$columns] as $row) {
            $content .= '% ' . implode(',', $row) . "\n";
        }
        foreach ($rows as $row) {
            $content .= '% ' . implode(',', array_map(static fn ($c) => (string) $row[$c], $columns)) . "\n";
        }
        return $content;
    }

    public function filePath(string $storedPath): string
    {
        return $this->config->exportDir() . DIRECTORY_SEPARATOR . $storedPath;
    }
}
