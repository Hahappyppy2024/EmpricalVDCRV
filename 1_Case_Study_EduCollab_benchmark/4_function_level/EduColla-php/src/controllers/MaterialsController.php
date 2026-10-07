<?php
require_once APP_BASE . '/src/controllers/_guards.php';
require_once APP_BASE . '/src/repos/MaterialRepo.php';

class MaterialsController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    private function courseMaterialsDir(int $courseId): string {
        $base = APP_BASE . '/storage/materials';
        if (!is_dir($base)) mkdir($base, 0777, true);
        $dir = $base . '/course_' . $courseId;
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return $dir;
    }

    private function sanitizeZipEntry(string $name): bool {
        // Reject absolute paths and traversal
        if ($name === '' || $name === '.' || $name === '..') return false;
        $name = str_replace('\\', '/', $name);
        if (strpos($name, "\0") !== false) return false;
        if (preg_match('/^[A-Za-z]:\//', $name)) return false; // Windows drive
        if (str_starts_with($name, '/') || str_starts_with($name, '../') || strpos($name, '/../') !== false) return false;
        return true;
    }

    private function safeExtractZip(string $zipFile, string $destDir): array {
        $za = new ZipArchive();
        if ($za->open($zipFile) !== true) return [false, 'cannot_open_zip'];

        // Validate entries
        for ($i = 0; $i < $za->numFiles; $i++) {
            $stat = $za->statIndex($i);
            $name = $stat['name'] ?? '';
            if (!$this->sanitizeZipEntry($name)) {
                $za->close();
                return [false, 'zip_entry_rejected'];
            }
        }

        // Extract
        if (!is_dir($destDir)) mkdir($destDir, 0777, true);
        if (!$za->extractTo($destDir)) {
            $za->close();
            return [false, 'extract_failed'];
        }
        $za->close();

        // Post-check: ensure extracted files remain under destDir
        $destReal = realpath($destDir);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $r = realpath($f->getPathname());
            if ($destReal === false || $r === false || strpos($r, $destReal) !== 0) {
                return [false, 'extract_boundary_violation'];
            }
        }
        return [true, 'ok'];
    }

    private function listFiles(string $dir): array {
        $out = [];
        if (!is_dir($dir)) return $out;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isDir()) continue;
            $rel = substr($f->getPathname(), strlen($dir) + 1);
            $rel = str_replace('\\', '/', $rel);
            $out[] = [
                'path' => $rel,
                'size' => $f->getSize(),
                'mtime' => gmdate('c', $f->getMTime())
            ];
        }
        sort($out);
        return $out;
    }

    // HTML: list materials
    public function index(array $params): void {
        $user = require_login();
        $courseId = (int)$params['course_id'];
        require_course_member($this->pdo, $courseId, $user, false);

        $mrepo = new MaterialRepo($this->pdo);
        $materials = $mrepo->listByCourse($courseId);
        $files = $this->listFiles($this->courseMaterialsDir($courseId));

        render('materials/index', [
            'user' => $user,
            'course_id' => $courseId,
            'materials' => $materials,
            'files' => $files,
        ]);
    }

    public function uploadForm(array $params): void {
        $user = require_login();
        $courseId = (int)$params['course_id'];
        require_course_staff($this->pdo, $courseId, $user, false);
        render('materials/upload_zip', [
            'user' => $user,
            'course_id' => $courseId,
        ]);
    }

    // HTML: POST upload zip (auto-extract)
    public function uploadZip(array $params): void {
        $user = require_login();
        $courseId = (int)$params['course_id'];
        require_course_staff($this->pdo, $courseId, $user, false);

        if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
            flash('Upload failed');
            redirect('/courses/' . $courseId . '/materials');
        }

        $orig = $_FILES['zip']['name'] ?? 'materials.zip';
        $tmp = $_FILES['zip']['tmp_name'];

        $courseDir = $this->courseMaterialsDir($courseId);
        $zipStoreDir = APP_BASE . '/storage/materials_zips';
        if (!is_dir($zipStoreDir)) mkdir($zipStoreDir, 0777, true);
        $zipPath = $zipStoreDir . '/' . $courseId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.zip';
        if (!move_uploaded_file($tmp, $zipPath)) {
            flash('Upload move failed');
            redirect('/courses/' . $courseId . '/materials');
        }

        // Extract into course materials dir
        $extractDir = $courseDir; // extract directly into course dir
        [$ok, $msg] = $this->safeExtractZip($zipPath, $extractDir);
        if (!$ok) {
            @unlink($zipPath);
            flash('Zip rejected: ' . $msg);
            redirect('/courses/' . $courseId . '/materials');
        }

        $mrepo = new MaterialRepo($this->pdo);
        $mrepo->create($courseId, (int)$user['user_id'], $orig, $zipPath, $extractDir);

        flash('Materials installed');
        redirect('/courses/' . $courseId . '/materials');
    }

    // HTML: download packed zip of course materials
    public function downloadZip(array $params): void {
        $user = require_login();
        $courseId = (int)$params['course_id'];
        require_course_member($this->pdo, $courseId, $user, false);

        $dir = $this->courseMaterialsDir($courseId);
        $tmpZip = APP_BASE . '/storage/tmp/materials_' . $courseId . '_' . time() . '.zip';
        if (!is_dir(dirname($tmpZip))) mkdir(dirname($tmpZip), 0777, true);

        $za = new ZipArchive();
        if ($za->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500); echo 'zip_failed'; return;
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isDir()) continue;
            $path = $f->getPathname();
            $rel = substr($path, strlen($dir) + 1);
            $rel = str_replace('\\', '/', $rel);
            $za->addFile($path, $rel);
        }
        $za->close();

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="course_' . $courseId . '_materials.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
    }

    // API: list materials + files
    public function apiIndex(array $params): void {
        $user = require_api_login();
        $courseId = (int)$params['course_id'];
        require_course_member($this->pdo, $courseId, $user, true);

        $mrepo = new MaterialRepo($this->pdo);
        $materials = $mrepo->listByCourse($courseId);
        $files = $this->listFiles($this->courseMaterialsDir($courseId));
        json_response(['materials' => $materials, 'files' => $files]);
    }

    // API: upload zip (multipart, field=zip)
    public function apiUploadZip(array $params): void {
        $user = require_api_login();
        $courseId = (int)$params['course_id'];
        require_course_staff($this->pdo, $courseId, $user, true);

        if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => 'upload_failed'], 400);
        }

        $orig = $_FILES['zip']['name'] ?? 'materials.zip';
        $tmp = $_FILES['zip']['tmp_name'];

        $courseDir = $this->courseMaterialsDir($courseId);
        $zipStoreDir = APP_BASE . '/storage/materials_zips';
        if (!is_dir($zipStoreDir)) mkdir($zipStoreDir, 0777, true);
        $zipPath = $zipStoreDir . '/' . $courseId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.zip';
        if (!move_uploaded_file($tmp, $zipPath)) {
            json_response(['error' => 'move_failed'], 400);
        }

        [$ok, $msg] = $this->safeExtractZip($zipPath, $courseDir);
        if (!$ok) {
            @unlink($zipPath);
            json_response(['error' => 'zip_rejected', 'reason' => $msg], 400);
        }

        $mrepo = new MaterialRepo($this->pdo);
        $id = $mrepo->create($courseId, (int)$user['user_id'], $orig, $zipPath, $courseDir);

        json_response(['material' => ['material_id' => $id, 'original_name' => $orig]]);
    }

    // API: download packed zip
    public function apiDownloadZip(array $params): void {
        $user = require_api_login();
        $courseId = (int)$params['course_id'];
        require_course_member($this->pdo, $courseId, $user, true);

        // Reuse same implementation as HTML
        $this->downloadZip($params);
        exit;
    }
}
