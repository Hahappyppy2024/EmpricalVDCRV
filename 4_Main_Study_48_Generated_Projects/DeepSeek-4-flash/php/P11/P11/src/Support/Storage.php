<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves the local storage root shared by file management, backups,
 * restore logs and the seed fixtures.
 */
final class Storage
{
    public static function path(): string
    {
        $root = dirname(__DIR__, 2);
        $env = getenv('STORAGE_DIR');

        return $env ?: $root . '/storage';
    }

    public static function filesDir(int $userId): string
    {
        $dir = self::path() . '/files/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
