<?php
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/repos/GradeRepo.php';
require_once APP_BASE . '/src/repos/UserRepo.php';
require_once APP_BASE . '/src/repos/AssignmentRepo.php';
require_once APP_BASE . '/src/repos/SubmissionRepo.php';

class CsvController {
    private PDO $pdo;
    private MembershipRepo $members;
    private UserRepo $users;
    private AssignmentRepo $assignments;
    private SubmissionRepo $subs;

    function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->members = new MembershipRepo($pdo);
        $this->users = new UserRepo($pdo);
        $this->assignments = new AssignmentRepo($pdo);
        $this->subs = new SubmissionRepo($pdo);
    }

    function exportMembers(array $p): void {
        $user = require_login();
        $course_id = (int)$p['course_id'];
        $rows = $this->members->listByCourse($course_id);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="members_course_' . $course_id . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['user_id','username','display_name','role_in_course']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['user_id'], $r['username'], $r['display_name'], $r['role_in_course']]);
        }
        fclose($out);
        exit;
    }

    function importMembers(array $p): void {
        $user = require_login();
        $course_id = (int)$p['course_id'];

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400); echo "upload error"; return;
        }
        $tmp = $_FILES['csv_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) { http_response_code(400); echo "cannot read"; return; }

        $header = fgetcsv($fh);
        // Expect columns: username, display_name, role_in_course (role optional)
        while (($row = fgetcsv($fh)) !== false) {
            $data = array_combine($header, $row);
            $username = trim($data['username'] ?? '');
            $display = trim($data['display_name'] ?? $username);
            $role = trim($data['role_in_course'] ?? 'student');
            if ($username === '') continue;

            $u = $this->users->getByUsername($username);
            if (!$u) {
                $uid = $this->users->create($username, password_hash('student123', PASSWORD_DEFAULT), $display);
            } else {
                $uid = (int)$u['user_id'];
            }
            // naive: allow role from CSV
            $this->members->add($course_id, $uid, $role);
        }
        fclose($fh);
        redirect('/courses/' . $course_id . '/members');
    }

    function exportAssignmentGrades(array $p): void {
        $user = require_login();
        $course_id = (int)$p['course_id'];
        $assignment_id = (int)$p['assignment_id'];

        $assignment = $this->assignments->get($assignment_id);
        if (!$assignment) { http_response_code(404); echo "not found"; return; }

        $rows = $this->subs->listByAssignment($course_id, $assignment_id);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="grades_course_' . $course_id . '_assignment_' . $assignment_id . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['submission_id','student_id','student_username','content_text','attachment_upload_id','created_at']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['submission_id'],$r['student_id'],$r['student_username'],$r['content_text'],$r['attachment_upload_id'],$r['created_at']]);
        }
        fclose($out);
        exit;
    }

    // API variants
    function apiExportMembers(array $p): void { $this->exportMembers($p); }
    function apiImportMembers(array $p): void { $this->importMembers($p); }
//    function apiExportAssignmentGrades(array $p): void { $this->exportAssignmentGrades($p); }

    public function apiExportAssignmentGrades(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, true);

        // gather rows (submissions + usernames)
        $srepo = new SubmissionRepo($this->pdo);
        $subs = $srepo->listForAssignment($courseId, $assignmentId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="grades_' . $courseId . '_' . $assignmentId . '.csv"');

        $out = fopen('php://output', 'w');
        // PHP 8.4/8.5 建议显式给 escape（避免 deprecated）
        $escape = "\\";
        fputcsv($out, ['submission_id', 'student_id', 'student_username', 'grade', 'feedback'], ',', '"', $escape);

        foreach ($subs as $s) {
            // 你如果还没实现 grade 表，这里可以先输出空 grade/feedback，确保 CSV 生成
            $grade = $s['grade'] ?? '';
            $feedback = $s['feedback'] ?? '';
            fputcsv($out, [
                $s['submission_id'],
                $s['student_id'],
                $s['student_username'] ?? '',
                $grade,
                $feedback
            ], ',', '"', $escape);
        }

        fclose($out);
        exit;
    }

// ---------- Assignment grades CSV (v1) ----------
public function importAssignmentGrades(array $p) {
    require_login();
    $courseId = intval($p['course_id']);
    $assignmentId = intval($p['assignment_id']);
    // minimal: accept CSV upload in multipart form field 'file'
    if (empty($_FILES['file']['tmp_name'])) { http_response_code(400); echo "missing file"; return; }
    $tmp = $_FILES['file']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) { http_response_code(400); echo "cannot read"; return; }
    $header = fgetcsv($fh);
    // expected columns: username,score,feedback
    $repoM = new MembershipRepo($this->pdo);
    $members = $repoM->listMembers($courseId);
    $userByUsername = [];
    foreach ($members as $m) { $userByUsername[$m['username']] = intval($m['user_id']); }

    $repoG = new GradeRepo($this->pdo);
    while (($row = fgetcsv($fh)) !== false) {
        $username = $row[0] ?? '';
        $score = $row[1] ?? '';
        $feedback = $row[2] ?? '';
        if (!$username || !isset($userByUsername[$username])) continue;
        $studentId = $userByUsername[$username];
        $repoG->upsertGrade($courseId, $assignmentId, $studentId, $score, $feedback);
    }
    fclose($fh);
    redirect("/courses/$courseId/assignments/$assignmentId");
}

    public function apiImportAssignmentGrades(array $p) {
        require_api_login();
        $courseId = intval($p['course_id']);
        $assignmentId = intval($p['assignment_id']);

        // Accept multiple common field names
        $f = $_FILES['file'] ?? $_FILES['csv_file'] ?? $_FILES['csv'] ?? $_FILES['upload'] ?? null;
        if (!$f || empty($f['tmp_name'])) return json_out(['error' => 'missing file'], 400);

        $tmp = $f['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) return json_out(['error' => 'cannot read'], 400);

        $header = fgetcsv($fh);
        if (!$header) { fclose($fh); return json_out(['error' => 'empty csv'], 400); }

        // Normalize header
        $h = array_map(fn($x) => strtolower(trim((string)$x)), $header);

        $repoM = new MembershipRepo($this->pdo);
        $members = $repoM->listMembers($courseId);

        $userByUsername = [];
        foreach ($members as $m) { $userByUsername[$m['username']] = intval($m['user_id']); }

        $repoG = new GradeRepo($this->pdo);

        // Optional: if submission_id mode, we need submission_id -> student_id mapping
        $subToStudent = [];
        if (in_array('submission_id', $h, true)) {
            // Adjust table/column names if your schema differs
            $st = $this->pdo->prepare("SELECT submission_id, student_id FROM submissions WHERE course_id=? AND assignment_id=?");
            $st->execute([$courseId, $assignmentId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $subToStudent[(int)$r['submission_id']] = (int)$r['student_id'];
            }
        }

        // Figure out column indices
        $idxUsername = array_search('username', $h, true);
        $idxSubId    = array_search('submission_id', $h, true);
        $idxScore    = array_search('score', $h, true);
        $idxFeedback = array_search('feedback', $h, true);

        // Fallback to position if header unknown
        if ($idxScore === false) $idxScore = 1;
        if ($idxFeedback === false) $idxFeedback = 2;

        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $score = $row[$idxScore] ?? '';
            $feedback = $row[$idxFeedback] ?? '';

            $studentId = null;

            // Mode 1: username
            if ($idxUsername !== false) {
                $username = trim((string)($row[$idxUsername] ?? ''));
                if ($username !== '' && isset($userByUsername[$username])) {
                    $studentId = $userByUsername[$username];
                }
            }
            // Mode 2: submission_id
            else if ($idxSubId !== false) {
                $sid = (int)($row[$idxSubId] ?? 0);
                if ($sid > 0 && isset($subToStudent[$sid])) {
                    $studentId = $subToStudent[$sid];
                }
            }
            // Mode 3: legacy positional username in col0
            else {
                $username = trim((string)($row[0] ?? ''));
                if ($username !== '' && isset($userByUsername[$username])) {
                    $studentId = $userByUsername[$username];
                }
            }

            if (!$studentId) continue;

            $repoG->upsertGrade($courseId, $assignmentId, $studentId, $score, $feedback);
            $count++;
        }

        fclose($fh);
        return json_out(['imported' => $count], 200);
    }


}
