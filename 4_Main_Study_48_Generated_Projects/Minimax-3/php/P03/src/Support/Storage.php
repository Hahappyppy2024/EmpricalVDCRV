<?php
declare(strict_types=1);

namespace Shop\Support;

final class Storage
{
    public static function saveUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload error.'];
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'File too large.'];
        }
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        $info = getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info['mime'], $allowed, true)) {
            return ['ok' => false, 'error' => 'Only PNG, JPEG, GIF or WebP allowed.'];
        }
        $ext = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ][$info['mime']];

        $root = \Shop\Config::get('APP_ROOT', dirname(__DIR__));
        $dir = $root . '/data/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Failed to save upload.'];
        }
        return ['ok' => true, 'path' => '/data/uploads/' . $name];
    }

    public static function placeImage(string $sku): string
    {
        $palette = ['#0ea5e9', '#22c55e', '#f97316', '#a855f7', '#ef4444', '#0f766e'];
        $hash = 0;
        for ($i = 0; $i < strlen($sku); $i++) {
            $hash = ($hash * 31 + ord($sku[$i])) & 0xffff;
        }
        $color = $palette[$hash % count($palette)];
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="240" viewBox="0 0 320 240">'
            . '<rect width="320" height="240" fill="' . $color . '"/>'
            . '<text x="50%" y="50%" font-family="Verdana,Arial,sans-serif" font-size="28" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">'
            . htmlspecialchars(substr($sku, 0, 12), ENT_QUOTES, 'UTF-8')
            . '</text></svg>';
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}