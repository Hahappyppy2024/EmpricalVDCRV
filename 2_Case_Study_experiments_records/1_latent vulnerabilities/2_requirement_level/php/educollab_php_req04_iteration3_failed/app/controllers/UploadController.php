<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class UploadController
{
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    /**
     * @var array<string, string[]>
     */
    private const ALLOWED_EXTENSIONS = [
        'txt' => ['text/plain'],
        'md' => ['text/markdown', 'text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
    ];

    public function __construct(
        private UploadRepository $uploadRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    private function storageRoot(): string
    {
        $path = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    private function detectMimeType(string $tmpPath): ?string
    {
        if (!is_file($tmpPath)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    $normalized = strtolower(trim(explode(';', $mime, 2)[0]));
                    if ($normalized !== '') {
                        return $normalized;
                    }
                }
            }
        }

        return null;
    }

    private function validateUploadedFile(array $file): ?string
    {
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0) {
            return 'file is empty';
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            return 'file exceeds maximum size of 10 MB';
        }

        $original = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === '' || !isset(self::ALLOWED_EXTENSIONS[$extension])) {
            return 'file type is not allowed';
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mimeType = $this->detectMimeType($tmpName);
        if ($mimeType === null || !in_array($mimeType, self::ALLOWED_EXTENSIONS[$extension], true)) {
            return 'file type is not allowed';
        }

        return null;
    }

    private function saveUploadedFile(int $courseId, int $userId): array
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return ['success' => false, 'error' => 'file is required'];
        }
        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'upload failed'];
        }

        $validationError = $this->validateUploadedFile($file);
        if ($validationError !== null) {
            return ['success' => false, 'error' => $validationError];
        }

        $original = basename((string) ($file['name'] ?? 'upload.bin'));
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'upload.bin';
        $relative = $courseId . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        $absolute = $this->storageRoot() . '/' . $relative;

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $stored = move_uploaded_file($tmpName, $absolute);
        if (!$stored && is_file($tmpName) && PHP_SAPI === 'cli-server') {
            $stored = rename($tmpName, $absolute);
            if (!$stored) {
                $stored = copy($tmpName, $absolute);
                if ($stored) {
                    @unlink($tmpName);
                }
            }
        }
        if (!$stored) {
            return ['success' => false, 'error' => 'failed to store uploaded file'];
        }

        $uploadId = $this->uploadRepo->create([
            'course_id' => $courseId,
            'uploaded_by' => $userId,
            'original_name' => $original,
            'storage_path' => 'storage/uploads/' . $relative,
        ]);

        return ['success' => true, 'upload' => $this->uploadRepo->findById($courseId, $uploadId)];
    }

    public function showUploadForm(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/uploads/new', ['title' => 'Upload File', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function uploadHtml(int $courseId): void
    {
        $userId = require_login($this->config, false);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->saveUploadedFile($courseId, $userId);
        if (!$result['success']) {
            render('courses/uploads/new', ['title' => 'Upload File', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/uploads');
    }

    public function uploadApi(int $courseId): void
    {
        $userId = require_login($this->config, true);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->saveUploadedFile($courseId, $userId);
        if (!$result['success']) {
            json_response($result, 422);
        }
        json_response($result, 201);
    }

    public function listHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/uploads/index', [
            'title' => 'Uploads',
            'course' => $this->courseRepo->findById($courseId),
            'uploads' => $this->uploadRepo->listByCourse($courseId),
            'is_staff' => current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
        ]);
    }

    public function listApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'uploads' => $this->uploadRepo->listByCourse($courseId)]);
    }

    public function downloadHtml(int $courseId, int $uploadId): void
    {
        $this->downloadInternal($courseId, $uploadId, false);
    }

    public function downloadApi(int $courseId, int $uploadId): void
    {
        $this->downloadInternal($courseId, $uploadId, true);
    }

    private function downloadInternal(int $courseId, int $uploadId, bool $api): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, $api);
        $upload = $this->uploadRepo->findById($courseId, $uploadId);
        if (!$upload) {
            if ($api) {
                json_response(['success' => false, 'error' => 'Upload not found'], 404);
            }
            http_response_code(404);
            echo 'Upload not found';
            exit;
        }
        $path = dirname(__DIR__, 2) . '/' . ltrim($upload['storage_path'], '/');
        if (!is_file($path)) {
            if ($api) {
                json_response(['success' => false, 'error' => 'File not found'], 404);
            }
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($upload['original_name']) . '"');
        readfile($path);
        exit;
    }

    public function deleteHtml(int $courseId, int $uploadId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->deleteInternal($courseId, $uploadId);
        redirect_to('/courses/' . $courseId . '/uploads');
    }

    public function deleteApi(int $courseId, int $uploadId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->deleteInternal($courseId, $uploadId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function deleteInternal(int $courseId, int $uploadId): array
    {
        $upload = $this->uploadRepo->findById($courseId, $uploadId);
        if (!$upload) {
            return ['success' => false, 'error' => 'Upload not found'];
        }
        $path = dirname(__DIR__, 2) . '/' . ltrim($upload['storage_path'], '/');
        if (is_file($path)) {
            @unlink($path);
        }
        $this->uploadRepo->delete($courseId, $uploadId);
        return ['success' => true];
    }
}