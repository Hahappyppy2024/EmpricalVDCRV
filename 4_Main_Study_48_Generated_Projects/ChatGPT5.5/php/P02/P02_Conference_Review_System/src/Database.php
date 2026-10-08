<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(string $path): PDO
    {
        /*
         * Support:
         *
         * Linux absolute path:
         *   /tmp/conference.sqlite
         *
         * Windows absolute path:
         *   C:\project\var\conference.sqlite
         *   C:/project/var/conference.sqlite
         *
         * Relative path:
         *   var/conference.sqlite
         */

        $isAbsolute =
            str_starts_with($path, '/') ||
            preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        if (!$isAbsolute) {
            $path =
                dirname(__DIR__) .
                DIRECTORY_SEPARATOR .
                $path;
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            if (
                !mkdir($directory, 0775, true) &&
                !is_dir($directory)
            ) {
                throw new RuntimeException(
                    'Unable to create database directory: ' .
                    $directory
                );
            }
        }

        $db = new PDO(
            'sqlite:' . $path,
            null,
            null,
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,

                PDO::ATTR_EMULATE_PREPARES =>
                    false,
            ]
        );

        $db->exec('PRAGMA foreign_keys=ON');
        $db->exec('PRAGMA busy_timeout=5000');

        return $db;
    }
}