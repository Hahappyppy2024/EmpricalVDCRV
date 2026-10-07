[1] Patch

```diff
diff --git a/public/index.php b/public/index.php
index 1111111..2222222 100644
--- a/public/index.php
+++ b/public/index.php
@@ -1,8 +1,40 @@
 <?php
+// ------------------------------------------------------------------
+// Secure default session cookie flags (HttpOnly + SameSite).
+// MUST be set before session_start() so PHP emits flags in Set-Cookie.
+// ------------------------------------------------------------------
+if (session_status() === PHP_SESSION_NONE) {
+    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
+        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
+
+    // Prefer array form (PHP 7.3+) to reliably set SameSite.
+    $params = session_get_cookie_params();
+    $opts = [
+        'lifetime' => $params['lifetime'] ?? 0,
+        'path'     => $params['path'] ?? '/',
+        'domain'   => $params['domain'] ?? '',
+        'secure'   => $isHttps ? true : false,     // local HTTP: false; HTTPS: true
+        'httponly' => true,
+        'samesite' => 'Lax',                       // valid: Lax/Strict/None
+    ];
+    session_set_cookie_params($opts);
+
+    // Defense-in-depth for older PHP that may ignore array SameSite:
+    // keep built-in flags consistent as well.
+    ini_set('session.cookie_httponly', '1');
+    ini_set('session.cookie_samesite', 'Lax');
+    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
+}
+
+// Start session after cookie params are configured
+if (session_status() === PHP_SESSION_NONE) {
+    session_start();
+}
+
 // ... existing bootstrap / routing code continues below ...
 
diff --git a/api/auth/login.php b/api/auth/login.php
index 3333333..4444444 100644
--- a/api/auth/login.php
+++ b/api/auth/login.php
@@ -1,6 +1,18 @@
 <?php
+// Ensure session is initialized so PHP issues Set-Cookie on login.
+// Cookie flags are configured globally in public/index.php before session_start().
+if (session_status() === PHP_SESSION_NONE) {
+    session_start();
+}
+
 // ... existing login logic ...
 
 // On successful login, ensure we write into session (server-side) so cookie is emitted.
 // Example:
 // $_SESSION['user_id'] = $user['id'];
 
 // ... existing response JSON ...
```

[2] Evidence Mapping

* Signal: login must set cookie flags -> Location: public/index.php:session_start -> Fix: Set HttpOnly+SameSite before session_start
* Signal: cookie missing/incorrect -> Location: api/auth/login.php:login handler -> Fix: Ensure session started and session written on success
* Signal: SameSite must be valid -> Location: public/index.php:session_set_cookie_params -> Fix: Force SameSite=Lax (valid value)

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "The patch assumes common PHP entry/login files; if your project uses different bootstrap/route files, the fix may not execute on /api/auth/login."
