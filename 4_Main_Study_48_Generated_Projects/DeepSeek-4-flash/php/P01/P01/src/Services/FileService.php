<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Deterministic local file adapter. Uploaded files are stored on the local
 * filesystem under the configured upload directory with a random stored name
 * and the original name preserved in the database.
 */
final class FileService
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @param array<string, mixed> $upload An entry from $_FILES
     * @param int $maxBytes
     * @return array{error?: string, stored_path?: string, filename?: string, mime_type?: string, size_bytes?: int}
     */
    public function storeUpload(array $upload, string $prefix = 'upload', int $maxBytes = 25 * 1024 * 1024): array
    {
        if (!isset($upload['name'], $upload['tmp_name']) || $upload['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No file was uploaded or the upload failed'];
        }
        if ($upload['size'] > $maxBytes) {
            return ['error' => 'The uploaded file exceeds the maximum allowed size'];
        }
        $original = is_array($upload['name']) ? '' : (string) $upload['name'];
        if ($original === '') {
            return ['error' => 'The uploaded file has no name'];
        }
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $stored = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $this->config->uploadDir() . DIRECTORY_SEPARATOR . $stored;
        $tmpName = is_array($upload['tmp_name']) ? '' : (string) $upload['tmp_name'];
        if (!move_uploaded_file($tmpName, $destination)) {
            return ['error' => 'The uploaded file could not be stored'];
        }
        return [
            'stored_path' => $stored,
            'filename' => $original,
            'mime_type' => (string) ($upload['type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) $upload['size'],
        ];
    }

    public function absolutePath(string $storedPath): string
    {
        return $this->config->uploadDir() . DIRECTORY_SEPARATOR . $storedPath;
    }

    /**
     * Store a PSR-7 uploaded file (used by the Slim JSON API layer).
     *
     * @return array{error?: string, stored_path?: string, filename?: string, mime_type?: string, size_bytes?: int}
     */
    public function storePsr7(UploadedFileInterface $file, string $prefix = 'upload', int $maxBytes = 25 * 1024 * 1024): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'No file was uploaded or the upload failed'];
        }
        if ($file->getSize() > $maxBytes) {
            return ['error' => 'The uploaded file exceeds the maximum allowed size'];
        }
        $original = (string) $file->getClientFilename();
        if ($original === '') {
            return ['error' => 'The uploaded file has no name'];
        }
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $stored = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $this->config->uploadDir() . DIRECTORY_SEPARATOR . $stored;
        try {
            $file->moveTo($destination);
        } catch (\Throwable $e) {
            return ['error' => 'The uploaded file could not be stored'];
        }
        return [
            'stored_path' => $stored,
            'filename' => $original,
            'mime_type' => (string) $file->getClientMediaType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }
}
