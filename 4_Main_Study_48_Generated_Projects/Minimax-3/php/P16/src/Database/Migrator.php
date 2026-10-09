<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Applies the bundled schema and (optionally) drops/rebuilds the file.
 */
final class Migrator
{
    public function __construct(private string $schemaPath) {}

    public function fresh(bool $dropExisting): void
    {
        $path = Connection::path();
        if ($path === '') {
            throw new \RuntimeException('Database path not configured.');
        }
        if ($dropExisting) {
            // Drop the cached PDO so the file can be removed.
            Connection::reset();
            if (file_exists($path)) {
                @unlink($path);
            }
            $wal = $path . '-wal';
            $shm = $path . '-shm';
            if (file_exists($wal)) {
                @unlink($wal);
            }
            if (file_exists($shm)) {
                @unlink($shm);
            }
            // Re-establish a connection to the same path.
            Connection::configure($path);
        }
        $pdo = Connection::get();
        $sql = require $this->schemaPath;
        if (!is_string($sql) || $sql === '') {
            throw new \RuntimeException('Schema file is empty or invalid.');
        }
        $pdo->exec($sql);
    }

    public function migrate(): void
    {
        $this->fresh(false);
    }
}