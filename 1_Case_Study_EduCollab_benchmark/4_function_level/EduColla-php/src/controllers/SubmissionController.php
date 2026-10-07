<?php
require_once APP_BASE . '/src/repos/SubmissionRepo.php';
require_once APP_BASE . '/src/repos/AssignmentRepo.php';
require_once APP_BASE . '/src/repos/UploadRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class SubmissionController {
    public function __construct(private PDO $pdo) {}

    public function showSubmit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, false);

        $arepo = new AssignmentRepo($this->pdo);
        $a = $arepo->getById($courseId, $assignmentId);
        if (!$a) { http_response_code(404); echo 'Not found'; return; }

        $srepo = new SubmissionRepo($this->pdo);
        $mine = $srepo->getMine($courseId, $assignmentId, (int)$u['user_id']);
        $uploads = (new UploadRepo($this->pdo))->listByCourse($courseId);

        render('submissions/submit', [
            'title' => 'Submit',
            'course_id' => $courseId,
            'assignment' => $a,
            'submission' => $mine,
            'uploads' => $uploads
        ]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, false);

        $content = trim($_POST['content_text'] ?? '');
        $att = trim($_POST['attachment_upload_id'] ?? '');
        $attId = $att === '' ? null : (int)$att;

        $repo = new SubmissionRepo($this->pdo);
        $repo->create($courseId, $assignmentId, (int)$u['user_id'], $content, $attId);

        redirect("/courses/$courseId/assignments/$assignmentId");
    }

    public function updateMine(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        $submissionId = (int)$p['submission_id'];
        require_course_student($this->pdo, $courseId, $u, false);

        $content = trim($_POST['content_text'] ?? '');
        $att = trim($_POST['attachment_upload_id'] ?? '');
        $attId = $att === '' ? null : (int)$att;

        $repo = new SubmissionRepo($this->pdo);
        $repo->update($courseId, $assignmentId, $submissionId, (int)$u['user_id'], $content, $attId);

        redirect("/courses/$courseId/assignments/$assignmentId");
    }

    public function listForAssignment(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, false);

        $repo = new SubmissionRepo($this->pdo);
        $subs = $repo->listForAssignment($courseId, $assignmentId);

        render('submissions/list_for_assignment', [
            'title' => 'Submissions',
            'course_id' => $courseId,
            'assignment_id' => $assignmentId,
            'submissions' => $subs
        ]);
    }

    public function mySubmissions(): void {
        $u = require_login();
        $repo = new SubmissionRepo($this->pdo);
        render('submissions/my', [
            'title' => 'My Submissions',
            'submissions' => $repo->listMine((int)$u['user_id'])
        ]);
    }

    public function apiGetOne(array $p) {
        $me = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        $submissionId = (int)$p['submission_id'];

        // load submission
        $st = $this->pdo->prepare(
            "SELECT submission_id, course_id, assignment_id, student_id, content_text, created_at, updated_at
         FROM submissions
         WHERE submission_id=? AND course_id=? AND assignment_id=?"
        );
        $st->execute([$submissionId, $courseId, $assignmentId]);
        $sub = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sub) return json_out(['error' => 'not_found'], 404);

        // permission: admin/staff OR owner
        $isAdmin = (($me['username'] ?? '') === 'admin');

        $repoM = new MembershipRepo($this->pdo);
        $m = $repoM->getByCourseAndUser($courseId, (int)$me['user_id']); // 你项目里可能叫 getByCourseAndUser
        $role = $m['role_in_course'] ?? '';

        $isStaff = $isAdmin || in_array($role, ['teacher', 'assistant', 'senior-assistant'], true);
        $isOwner = ((int)$sub['student_id'] === (int)$me['user_id']);

        if (!$isStaff && !$isOwner) {
            // choose one:
            return json_out(['error' => 'forbidden'], 403);
            // or: return json_out(['error' => 'not_found'], 404);
        }

        return json_out(['submission' => $sub], 200);
    }

    // ----------------------------
    // API
    // ----------------------------
    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, true);

        $data = parse_json_body();
        $content = trim($data['content_text'] ?? '');
        $att = $data['attachment_upload_id'] ?? null;
        $attId = ($att === null || $att === '') ? null : (int)$att;

        $s = (new SubmissionRepo($this->pdo))->create($courseId, $assignmentId, (int)$u['user_id'], $content, $attId);
        json_response(['submission' => $s]);
    }

//  R01  test_student_cannot_update_other_students_submission_strict
    public function apiUpdate(array $p): void {
        $me = require_api_login();

        $courseId = (int)($p['course_id'] ?? 0);
        $assignmentId = (int)($p['assignment_id'] ?? 0);
        $submissionId = (int)($p['submission_id'] ?? 0);

        // 1) Load submission (ensure it belongs to the course + assignment)
        $st = $this->pdo->prepare(
            "SELECT submission_id, course_id, assignment_id, student_id, content_text, attachment_upload_id, created_at, updated_at
         FROM submissions
         WHERE submission_id=? AND course_id=? AND assignment_id=?"
        );
        $st->execute([$submissionId, $courseId, $assignmentId]);
        $sub = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sub) {
            json_out(['error' => 'not_found'], 404);
            return;
        }

        // 2) Determine role in course (staff/admin) OR owner
        $isAdmin = (($me['username'] ?? '') === 'admin');

        // membership lookup (avoid relying on missing repo helpers)
        $role = '';
        $stm = $this->pdo->prepare("SELECT role_in_course FROM memberships WHERE course_id=? AND user_id=?");
        $stm->execute([$courseId, (int)$me['user_id']]);
        $m = $stm->fetch(PDO::FETCH_ASSOC);
        if ($m && isset($m['role_in_course'])) $role = (string)$m['role_in_course'];

        $isStaff = $isAdmin || in_array($role, ['teacher', 'assistant', 'senior-assistant'], true);
        $isOwner = ((int)$sub['student_id'] === (int)$me['user_id']);
// R01
//        if (!$isStaff && !$isOwner) {
//            // choose 403 or 404 (404 hides existence)
//            json_out(['error' => 'forbidden'], 403);
//            return;
//        }
// R01

        // 3) Parse body
        $data = parse_json_body();
        $newContent = isset($data['content_text']) ? (string)$data['content_text'] : null;
        $newAttach = array_key_exists('attachment_upload_id', $data) ? $data['attachment_upload_id'] : null;

        // No-op protection: if no fields provided, reject
        if ($newContent === null && $newAttach === null) {
            json_out(['error' => 'no_fields'], 400);
            return;
        }

        // 4) If not staff, restrict fields (owner can only update own content/attachment)
        // (You can further restrict attachment updates if desired)
        $fields = [];
        $vals = [];

        if ($newContent !== null) {
            $fields[] = "content_text=?";
            $vals[] = $newContent;
        }

        if ($newAttach !== null) {
            // allow null to clear attachment, or integer to set
            if ($newAttach === null || $newAttach === '' ) {
                $fields[] = "attachment_upload_id=NULL";
            } else {
                $fields[] = "attachment_upload_id=?";
                $vals[] = (int)$newAttach;
            }
        }

        // Always update updated_at (ISO)
        $fields[] = "updated_at=?";
        $vals[] = now_iso();

        $vals[] = $submissionId;

        // 5) Update
        $sql = "UPDATE submissions SET " . implode(", ", $fields) . " WHERE submission_id=?";
        $u = $this->pdo->prepare($sql);
        $u->execute($vals);

        // 6) Return updated submission
        $st2 = $this->pdo->prepare(
            "SELECT submission_id, assignment_id, course_id, student_id, content_text, attachment_upload_id, created_at, updated_at
         FROM submissions WHERE submission_id=?"
        );
        $st2->execute([$submissionId]);
        $updated = $st2->fetch(PDO::FETCH_ASSOC);

        json_out(['submission' => $updated], 200);
    }
    public function apiListMine(): void {
        $u = require_api_login();
        $repo = new SubmissionRepo($this->pdo);
        json_response(['submissions' => $repo->listMine((int)$u['user_id'])]);
    }

    public function apiListForAssignment(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, true);

        $repo = new SubmissionRepo($this->pdo);
        json_response(['submissions' => $repo->listForAssignment($courseId, $assignmentId)]);
    }

    // ----------------------------------------
    // v1: unzip a submission attachment (zip)
    // ----------------------------------------
    public function apiUnzipSubmission(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        $submissionId = (int)$p['submission_id'];

        // Optional (recommended): only staff can unzip
        // require_course_staff($this->pdo, $courseId, $u, true);

        $repo = new SubmissionRepo($this->pdo);

        // FIX: getById expects (courseId, assignmentId, submissionId)
        $sub = $repo->getById($courseId, $assignmentId, $submissionId);
        if (!$sub) { json_response(['error' => 'not found'], 404); return; }

        if (empty($sub['attachment_upload_id'])) { json_response(['error' => 'no attachment'], 400); return; }

        $uploadRepo = new UploadRepo($this->pdo);
        $up = $uploadRepo->getById($courseId, (int)$sub['attachment_upload_id']);
        if (!$up) { json_response(['error' => 'upload not found'], 404); return; }

        $zipPath = APP_BASE . '/' . $up['storage_path'];
        if (!file_exists($zipPath)) { json_response(['error' => 'file missing'], 404); return; }

        $extractDir = APP_BASE . '/storage/submission_extracts/' . $submissionId;
        if (!is_dir($extractDir)) mkdir($extractDir, 0777, true);

        $za = new ZipArchive();
        if ($za->open($zipPath) !== true) { json_response(['error' => 'cannot open zip'], 400); return; }
        $za->extractTo($extractDir);
        $za->close();

        json_response(['ok' => true, 'extract_dir' => $extractDir]);
    }

    public function apiListSubmissionFiles(array $p): void {
        require_api_login();
        $submissionId = (int)$p['submission_id'];

        $extractDir = APP_BASE . '/storage/submission_extracts/' . $submissionId;
        $files = [];

        if (is_dir($extractDir)) {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir));
            foreach ($rii as $file) {
                /** @var SplFileInfo $file */
                if ($file->isDir()) continue;
                $files[] = str_replace($extractDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        json_response(['files' => $files]);
    }

    // ----------------------------------------
    // v1: export all submissions' attachments for an assignment
    // ----------------------------------------
    public function apiExportSubmissionsZip(array $p): void {
        require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];

        $repo = new SubmissionRepo($this->pdo);
        $subs = $repo->listForAssignment($courseId, $assignmentId);

        $jobId = uniqid('exp_', true);
        $outDir = APP_BASE . '/storage/exports';
        if (!is_dir($outDir)) mkdir($outDir, 0777, true);
        $zipPath = $outDir . '/' . $jobId . '.zip';

//        $za = new ZipArchive();
//        $za->open($zipPath, ZipArchive::CREATE);
        $za = new ZipArchive();
        $ok = $za->open($zipPath, ZipArchive::CREATE);
        if ($ok !== true) {
            json_response(['error' => 'zip_open_failed', 'code' => $ok, 'zipPath' => $zipPath], 500);
            return;
        }
        if (!is_dir($outDir)) {
            if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
                json_response(['error' => 'mkdir_failed', 'dir' => $outDir], 500);
                return;
            }
        }



        $uploadRepo = new UploadRepo($this->pdo);
        foreach ($subs as $s) {
            if (!empty($s['attachment_upload_id'])) {
                $up = $uploadRepo->getById($courseId, (int)$s['attachment_upload_id']);
                if ($up) {
                    $src = APP_BASE . '/' . $up['storage_path'];
                    if (file_exists($src)) {
                        $za->addFile($src, 'submission_' . $s['submission_id'] . '/' . basename($src));
                    }
                }
            }
        }
        $za->close();

        json_response(['job_id' => $jobId]);
    }

    public function apiDownloadSubmissionsZip(array $p): void {
        require_api_login();
        $jobId = (string)$p['job_id'];

        $zipPath = APP_BASE . '/storage/exports/' . $jobId . '.zip';
        if (!file_exists($zipPath)) { http_response_code(404); echo "not found"; return; }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="submissions_' . $jobId . '.zip"');
        readfile($zipPath);
        exit;
    }
}