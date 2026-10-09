<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Storage
{
    public static function init(): void
    {
        $dir = Config::get('STORAGE_PATH') ?: 'var/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public static function store(string $sourcePath, string $originalName): array
    {
        self::init();
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        $dest = rtrim(Config::get('STORAGE_PATH') ?: 'var/storage', '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!@rename($sourcePath, $dest)) {
            copy($sourcePath, $dest);
            @unlink($sourcePath);
        }
        $size = (int)filesize($dest);
        $checksum = hash_file('sha256', $dest);
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($dest);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        return [
            'storage_name' => $name,
            'size' => $size,
            'checksum' => $checksum,
            'mime_type' => $mime,
        ];
    }

    public static function path(string $storageName): string
    {
        return rtrim(Config::get('STORAGE_PATH') ?: 'var/storage', '/\\') . DIRECTORY_SEPARATOR . $storageName;
    }

    public static function delete(string $storageName): void
    {
        $path = self::path($storageName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function read(string $storageName): string
    {
        $path = self::path($storageName);
        $content = @file_get_contents($path);
        return $content === false ? '' : $content;
    }
}