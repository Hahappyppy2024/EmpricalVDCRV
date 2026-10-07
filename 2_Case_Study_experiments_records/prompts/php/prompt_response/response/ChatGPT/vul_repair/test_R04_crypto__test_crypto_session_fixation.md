[1] Patch

```diff
diff --git a/api/auth.php b/api/auth.php
index 1111111..2222222 100644
--- a/api/auth.php
+++ b/api/auth.php
@@ -1,10 +1,28 @@
 <?php
+// Ensure a session exists for both pre-login and post-login flows.
+if (session_status() !== PHP_SESSION_ACTIVE) {
+  session_start();
+}
+
 // ... existing includes / DB bootstrap ...
 
 // POST /api/auth/login
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^/api/auth/login$#', $_SERVER['REQUEST_URI'])) {
   $body = json_decode(file_get_contents('php://input'), true);
   $username = $body['username'] ?? '';
   $password = $body['password'] ?? '';
 
   // ... existing credential verification ...
   // if (!$ok) { http_response_code(401); echo json_encode([...]); exit; }
 
-  // establish auth session
+  // Establish auth session (then rotate session id to mitigate fixation)
   $_SESSION['user_id'] = $user['id'];
   $_SESSION['username'] = $user['username'];
+
+  // Session fixation mitigation: rotate the session identifier after login.
+  // true => delete old session data on disk; new PHPSESSID will be emitted.
+  session_regenerate_id(true);
 
   http_response_code(200);
   header('Content-Type: application/json');
   echo json_encode(['ok' => true, 'user' => ['id' => $user['id'], 'username' => $user['username']]]);
   exit;
 }
```

[2] Evidence Mapping

* Signal: `sid_after == sid_before` assertion -> Location: `POST /api/auth/login` -> Fix: `session_regenerate_id(true) after setting auth session`
* Signal: Test reads cookie jar + Set-Cookie consistency -> Location: `login response headers` -> Fix: `force new PHPSESSID via regeneration`
* Signal: Pre-login session exists (sid_before present) -> Location: `session bootstrap` -> Fix: `ensure session_start before login handler`

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Only tests were provided; file/route names may differ, so the patch might not hit the real login handler."
