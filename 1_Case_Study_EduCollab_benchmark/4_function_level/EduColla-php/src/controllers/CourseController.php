<?php
require_once APP_BASE . '/src/repos/CourseRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class CourseController {
    public function __construct(private PDO $pdo) {}

    public function list(): void {
        require_login();
        $repo = new CourseRepo($this->pdo);
        render('courses/list', ['title' => 'Courses', 'courses' => $repo->listAll()]);
    }

    public function showNew(): void {
        require_login();
        render('courses/new', ['title' => 'New Course']);
    }

    public function create(): void {
        $u = require_login();
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($title === '') {
            render('courses/new', ['title' => 'New Course', 'error' => 'title required']);
            return;
        }
        $repo = new CourseRepo($this->pdo);
        $course = $repo->create((int)$u['user_id'], $title, $desc);
        // Auto add creator as teacher (or admin for global admin)
        $mrepo = new MembershipRepo($this->pdo);
        $role = is_global_admin($u) ? 'admin' : 'teacher';
        $mrepo->add((int)$course['course_id'], (int)$u['user_id'], $role);
        redirect('/courses/' . $course['course_id']);
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) { http_response_code(404); echo 'Not found'; return; }
        // allow viewing course detail even if not member? keep simple: require membership
        require_course_member($this->pdo, $courseId, $u, false);
        render('courses/detail', ['title' => 'Course', 'course' => $course]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) { http_response_code(404); echo 'Not found'; return; }
        render('courses/edit', ['title' => 'Edit Course', 'course' => $course]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $repo = new CourseRepo($this->pdo);
        $repo->update($courseId, $title, $desc);
        redirect('/courses/' . $courseId);
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new CourseRepo($this->pdo);
        $repo->delete($courseId);
        redirect('/courses');
    }

    // API
    public function apiList(): void {
        require_api_login();
        $repo = new CourseRepo($this->pdo);
        json_response(['courses' => $repo->listAll()]);
    }

    public function apiCreate(): void {
        $u = require_api_login();
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        if ($title === '') json_response(['error' => 'title required'], 400);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->create((int)$u['user_id'], $title, $desc);
        $mrepo = new MembershipRepo($this->pdo);
        $role = is_global_admin($u) ? 'admin' : 'teacher';
        $mrepo->add((int)$course['course_id'], (int)$u['user_id'], $role);
        json_response(['course' => $course]);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) json_response(['error' => 'not found'], 404);
        json_response(['course' => $course]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $repo = new CourseRepo($this->pdo);
        $course = $repo->update($courseId, $title, $desc);
        json_response(['course' => $course]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $repo = new CourseRepo($this->pdo);
        $repo->delete($courseId);
        json_response(['ok' => true]);
    }

//    invite link

    private function json($data, $status=200){
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function apiCreateInvite($p) {
        // 1) require login + require admin/teacher（你按你项目角色规则）
        if (empty($_SESSION['user'])) return $this->json(['error'=>'Unauthorized'], 401);

        $courseId = (int)$p['course_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $role = $body['role_in_course'] ?? 'student';
        $ttl = (int)($body['ttl_minutes'] ?? 60);
        if ($ttl <= 0) $ttl = 60;

        // 2) 生成 token + expires_at
        $token = bin2hex(random_bytes(16)); // 32 hex
        $expiresAt = time() + $ttl * 60;

        // 3) 写入 invite 表（你需要有 invite_links 表）
        // schema建议: id, course_id, token, role_in_course, expires_at, used_count, max_uses, created_by
        $stmt = $this->pdo->prepare("INSERT INTO invite_links(course_id, token, role_in_course, expires_at, used_count, max_uses, created_by)
                                 VALUES(?,?,?,?,0,1,?)");
        $stmt->execute([$courseId, $token, $role, $expiresAt, $_SESSION['user']['user_id']]);

        return $this->json(['token'=>$token, 'expires_at'=>$expiresAt], 200);
    }

    public function apiJoinByInvite() {
        if (empty($_SESSION['user'])) return $this->json(['error'=>'Unauthorized'], 401);
        $me = $_SESSION['user'];

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $body['token'] ?? '';
        if (!$token) return $this->json(['error'=>'token required'], 400);

        // 1) 查 invite
        $stmt = $this->pdo->prepare("SELECT * FROM invite_links WHERE token=?");
        $stmt->execute([$token]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) return $this->json(['error'=>'invalid token'], 403);

        // 2) 过期检查（A06）
        if ((int)$inv['expires_at'] < time()) return $this->json(['error'=>'expired'], 403);

        // 3) 一次性/限次使用检查（A06）
        $used = (int)$inv['used_count'];
        $max = (int)$inv['max_uses'];
        if ($max > 0 && $used >= $max) return $this->json(['error'=>'token already used'], 409);

        $courseId = (int)$inv['course_id'];
        $role = $inv['role_in_course'] ?: 'student';

        // 4) 加入 membership（你需要按你 membership 表字段适配）
        // 先查是否已是成员，避免重复
        $check = $this->pdo->prepare("SELECT 1 FROM memberships WHERE course_id=? AND user_id=?");
        $check->execute([$courseId, $me['user_id']]);
        if (!$check->fetchColumn()) {
            $ins = $this->pdo->prepare("INSERT INTO memberships(course_id, user_id, role_in_course) VALUES(?,?,?)");
            $ins->execute([$courseId, $me['user_id'], $role]);
        }

        // 5) 标记 invite 使用次数 +1（A06）
        $upd = $this->pdo->prepare("UPDATE invite_links SET used_count = used_count + 1 WHERE id=?");
        $upd->execute([(int)$inv['id']]);

        return $this->json(['ok'=>true, 'course_id'=>$courseId, 'role_in_course'=>$role], 200);
    }
}

