[1] Patch

```diff
diff --git a/api/courses.php b/api/courses.php
new file mode 100644
index 0000000..9b2d9a1
--- /dev/null
+++ b/api/courses.php
@@ -0,0 +1,186 @@
+<?php
+// Minimal course-related API routes (including SQLi-safe post search).
+// Assumes a PDO instance is available via require __DIR__ . '/../lib/db.php';
+
+require_once __DIR__ . '/../lib/db.php';
+
+header('Content-Type: application/json; charset=utf-8');
+
+function _json($code, $payload) {
+  http_response_code($code);
+  echo json_encode($payload);
+  exit;
+}
+
+function _method() {
+  return $_SERVER['REQUEST_METHOD'] ?? 'GET';
+}
+
+function _path() {
+  $uri = $_SERVER['REQUEST_URI'] ?? '/';
+  $qpos = strpos($uri, '?');
+  return $qpos === false ? $uri : substr($uri, 0, $qpos);
+}
+
+function _segments() {
+  $p = trim(_path(), '/');
+  return $p === '' ? [] : explode('/', $p);
+}
+
+function _read_json_body_or_4xx() {
+  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
+  if (stripos($ct, 'application/json') === false) {
+    _json(415, ['error' => 'Unsupported Media Type']);
+  }
+  $raw = file_get_contents('php://input');
+  $data = json_decode($raw, true);
+  if (!is_array($data)) {
+    // Do not leak parser warnings/paths; return controlled 4xx.
+    _json(400, ['error' => 'Malformed JSON']);
+  }
+  return $data;
+}
+
+// --- Router: /api/courses/*
+$seg = _segments();
+
+// Expected shapes:
+//   POST /api/courses                         (create course; needs robust JSON handling)
+//   GET  /api/courses/{course_id}/search/posts?keyword=...
+
+if (count($seg) >= 2 && $seg[0] === 'api' && $seg[1] === 'courses') {
+  $m = _method();
+
+  // POST /api/courses
+  if ($m === 'POST' && count($seg) === 2) {
+    $body = _read_json_body_or_4xx();
+    $title = isset($body['title']) ? trim((string)$body['title']) : '';
+    if ($title === '') {
+      _json(422, ['error' => 'title required']);
+    }
+
+    // Minimal insert; keeps behavior controlled on DB errors.
+    try {
+      $stmt = $pdo->prepare('INSERT INTO courses (title) VALUES (:t)');
+      $stmt->execute([':t' => $title]);
+      _json(200, ['course_id' => (int)$pdo->lastInsertId(), 'title' => $title]);
+    } catch (Throwable $e) {
+      _json(400, ['error' => 'request failed']);
+    }
+  }
+
+  // GET /api/courses/{course_id}/search/posts
+  if ($m === 'GET' && count($seg) === 5 && is_numeric($seg[2]) && $seg[3] === 'search' && $seg[4] === 'posts') {
+    $course_id = (int)$seg[2];
+    $keyword = isset($_GET['keyword']) ? (string)$_GET['keyword'] : '';
+    // Treat keyword as plain data. Do NOT interpolate into SQL.
+    $like = '%' . $keyword . '%';
+
+    try {
+      $sql = <<<SQL
+SELECT id, course_id, user_id, title, body, created_at
+FROM posts
+WHERE course_id = :cid
+  AND (title LIKE :kw ESCAPE '\\' OR body LIKE :kw ESCAPE '\\')
+ORDER BY id DESC
+LIMIT 50
+SQL;
+      $stmt = $pdo->prepare($sql);
+      $stmt->execute([':cid' => $course_id, ':kw' => $like]);
+      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
+      _json(200, ['posts' => $rows]);
+    } catch (Throwable $e) {
+      // Controlled response; no DB error leakage.
+      _json(200, ['posts' => []]);
+    }
+  }
+}
+
+// Fallthrough: not found (keep minimal; no internal detail leakage).
+_json(404, ['error' => 'Not Found']);
diff --git a/lib/db.php b/lib/db.php
new file mode 100644
index 0000000..d73d1ef
--- /dev/null
+++ b/lib/db.php
@@ -0,0 +1,24 @@
+<?php
+// Minimal PDO bootstrap for SQLite (no dependency changes).
+// The app may already have its own DB bootstrap; if so, align this file with it.
+
+$dbPath = __DIR__ . '/../data/app.sqlite';
+$dsn = 'sqlite:' . $dbPath;
+
+try {
+  $pdo = new PDO($dsn);
+  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
+  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
+} catch (Throwable $e) {
+  // Do not leak internals
+  http_response_code(500);
+  header('Content-Type: application/json; charset=utf-8');
+  echo json_encode(['error' => 'DB unavailable']);
+  exit;
+}
```

[2] Evidence Mapping

* Signal: SQLi payload `"' OR 1=1 --"` returns unrelated titles -> Location: `GET /api/courses/{id}/search/posts` -> Fix: Use PDO prepare + bound params
* Signal: Broadening vs nonsense keyword (len(inj) > len(nonsense)) -> Location: posts search SQL `WHERE ... LIKE ...` -> Fix: Parameterize LIKE, never interpolate keyword
* Signal: Malformed JSON must be 4xx and no leak -> Location: `POST /api/courses` JSON parsing -> Fix: json_decode guard + 400/415 without details

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "No project code was provided, so file paths/routes may not match your PHP app wiring."
