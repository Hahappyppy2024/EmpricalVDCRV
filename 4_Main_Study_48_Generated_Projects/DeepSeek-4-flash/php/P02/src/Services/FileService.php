<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StoredFileRepository;
use Psr\Http\Message\UploadedFileInterface;

final class FileService
{
    public function __construct(
        private readonly StoredFileRepository $files,
        private readonly string $storagePath
    ) {
    }

    public function storeUpload(
        UploadedFileInterface $upload,
        int $submissionId,
        string $kind = 'manuscript'
    ): array {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['file' => 'file upload failed']]);
        }
        $mime = $upload->getClientMediaType() ?: 'application/pdf';
        $original = $upload->getClientFilename() ?: 'upload.pdf';
        $name = $this->safeName($original);
        $dir = $this->storagePath . DIRECTORY_SEPARATOR . 'manuscripts';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'submission_' . $submissionId . '_' . $name;
        $upload->moveTo($path);
        $size = filesize($path) ?: 0;

        $id = $this->files->insert([
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'kind' => $kind,
            'submission_id' => $submissionId,
        ]);
        return $this->files->getById($id);
    }

    public function registerFile(string $name, string $path, string $mime, int $size, string $kind, ?int $submissionId = null): array
    {
        $id = $this->files->insert([
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'kind' => $kind,
            'submission_id' => $submissionId,
        ]);
        return $this->files->getById($id);
    }

    public function exportDirectory(): string
    {
        $dir = $this->storagePath . DIRECTORY_SEPARATOR . 'exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?? 'upload.bin';
        return $name;
    }
}
