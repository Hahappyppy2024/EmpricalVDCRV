<?php

declare(strict_types=1);

namespace CloudFS\Services;

final class StorageService
{
    public function __construct(private string $storagePath)
    {
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }
    }

    public function store(string $contents, string $originalName): array
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
        $folder = gmdate('Y/m');
        $dir = rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fileName = hash('sha256', $safeName . '|' . bin2hex(random_bytes(8))) . '_' . $safeName;
        $relative = $folder . '/' . $fileName;
        file_put_contents($this->absolute($relative), $contents);
        $size = strlen($contents);
        return ['storage_path' => $relative, 'size_bytes' => $size];
    }

    public function storeFromUpload(string $uploadedTmpPath, string $originalName): array
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
        $folder = gmdate('Y/m');
        $dir = rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fileName = hash('sha256', $safeName . '|' . bin2hex(random_bytes(8))) . '_' . $safeName;
        $relative = $folder . '/' . $fileName;
        copy($uploadedTmpPath, $this->absolute($relative));
        return ['storage_path' => $relative, 'size_bytes' => (int) filesize($this->absolute($relative))];
    }

    public function read(string $relativePath): string
    {
        $abs = $this->absolute($relativePath);
        if (!is_file($abs)) {
            return '';
        }
        return (string) file_get_contents($abs);
    }

    public function absolute(string $relativePath): string
    {
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function delete(string $relativePath): void
    {
        $abs = $this->absolute($relativePath);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
