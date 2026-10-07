<?php
require_once APP_BASE . '/src/repos/UploadRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class UploadController {
    public function __construct(private PDO $pdo) {}

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new UploadRepo($this->pdo);
        render('uploads/list', ['title' => 'Uploads', 'course_id' => $courseId, 'uploads' => $repo->listByCourse($courseId)]);
    }

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        render('uploads/new', ['title' => 'Upload File', 'course_id' => $courseId]);
    }

    public function upload(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            render('uploads/new', ['title' => 'Upload File', 'course_id' => $courseId, 'error' => 'upload failed']);
            return;
        }
        $f = $_FILES['file'];
        $orig = $f['name'] ?? 'file';
        $tmp = $f['tmp_name'];

        $courseDir = APP_BASE . '/storage/uploads/' . $courseId;
        if (!is_dir($courseDir)) mkdir($courseDir, 0777, true);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $name = uniqid('up_', true) . ($ext ? ('.' . $ext) : '');
        $dest = $courseDir . '/' . $name;
        move_uploaded_file($tmp, $dest);
        $rel = 'storage/uploads/' . $courseId . '/' . $name;

        $repo = new UploadRepo($this->pdo);
        $repo->create($courseId, (int)$u['user_id'], $orig, $rel);
        redirect("/courses/$courseId/uploads");
    }

    public function download(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $uploadId = (int)$p['upload_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new UploadRepo($this->pdo);
        $up = $repo->getById($courseId, $uploadId);
        if (!$up) { http_response_code(404); echo 'Not found'; return; }
        $abs = APP_BASE . '/' . $up['storage_path'];
        if (!file_exists($abs)) { http_response_code(404); echo 'Not found'; return; }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($up['original_name']) . '"');
        readfile($abs);
        exit;
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $uploadId = (int)$p['upload_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new UploadRepo($this->pdo);
        $up = $repo->getById($courseId, $uploadId);
        if ($up) {
            $abs = APP_BASE . '/' . $up['storage_path'];
            if (file_exists($abs)) @unlink($abs);
            $repo->delete($courseId, $uploadId);
        }
        redirect("/courses/$courseId/uploads");
    }

    // API
    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new UploadRepo($this->pdo);
        json_response(['uploads' => $repo->listByCourse($courseId)]);
    }

    public function apiUpload(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => 'upload failed'], 400);
        }
        $f = $_FILES['file'];
        $orig = $f['name'] ?? 'file';
        $tmp = $f['tmp_name'];

        $courseDir = APP_BASE . '/storage/uploads/' . $courseId;
        if (!is_dir($courseDir)) mkdir($courseDir, 0777, true);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $name = uniqid('up_', true) . ($ext ? ('.' . $ext) : '');
        $dest = $courseDir . '/' . $name;
        move_uploaded_file($tmp, $dest);
        $rel = 'storage/uploads/' . $courseId . '/' . $name;

        $repo = new UploadRepo($this->pdo);
        $up = $repo->create($courseId, (int)$u['user_id'], $orig, $rel);
        json_response(['upload' => $up]);
    }

    public function apiDownload(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $uploadId = (int)$p['upload_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new UploadRepo($this->pdo);
        $up = $repo->getById($courseId, $uploadId);
        if (!$up) json_response(['error' => 'not found'], 404);
        $abs = APP_BASE . '/' . $up['storage_path'];
        if (!file_exists($abs)) json_response(['error' => 'not found'], 404);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($up['original_name']) . '"');
        readfile($abs);
        exit;
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $uploadId = (int)$p['upload_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $repo = new UploadRepo($this->pdo);
        $up = $repo->getById($courseId, $uploadId);
        if ($up) {
            $abs = APP_BASE . '/' . $up['storage_path'];
            if (file_exists($abs)) @unlink($abs);
            $repo->delete($courseId, $uploadId);
        }
        json_response(['ok' => true]);
    }
}

