[1] Patch

```diff
diff --git a/app.py b/app.py
new file mode 100644
index 0000000..7c3a2b1
--- /dev/null
+++ b/app.py
@@ -0,0 +1,75 @@
+from flask import Flask, jsonify, request
+from werkzeug.exceptions import BadRequest, HTTPException
+
+
+def create_app():
+    app = Flask(__name__)
+
+    # Ensure debug-style exception pages are not exposed via API responses
+    app.config.setdefault("DEBUG", False)
+    app.config.setdefault("TESTING", False)
+    app.config.setdefault("PROPAGATE_EXCEPTIONS", False)
+    app.config.setdefault("TRAP_HTTP_EXCEPTIONS", False)
+
+    @app.errorhandler(BadRequest)
+    def handle_bad_request(e):
+        # Malformed JSON commonly lands here when Content-Type is application/json
+        return jsonify({"error": "invalid_json"}), 400
+
+    @app.errorhandler(HTTPException)
+    def handle_http_exception(e):
+        # Sanitize all HTTP errors (avoid Werkzeug debug strings / paths)
+        code = getattr(e, "code", 500) or 500
+        name = getattr(e, "name", "error")
+        return jsonify({"error": name.lower().replace(" ", "_")}), code
+
+    @app.errorhandler(Exception)
+    def handle_unexpected_exception(e):
+        # Never leak stack traces, file paths, DB errors, or secrets
+        return jsonify({"error": "internal_error"}), 500
+
+    # Optional: normalize JSON parsing for endpoints that rely on request.get_json()
+    @app.before_request
+    def _guard_malformed_json():
+        if request.method in ("POST", "PUT", "PATCH"):
+            ct = request.headers.get("Content-Type", "")
+            if "application/json" in ct.lower():
+                # Trigger parsing early so BadRequest handler produces a sanitized 400
+                request.get_json(silent=False)
+
+    return app
```

[2] Evidence Mapping

* Signal: POST /api/courses with malformed JSON -> Location: app.py:before_request -> Fix: Parse early to force sanitized 400
* Signal: Expected 400/415/422, got non-4xx -> Location: app.py:errorhandler(BadRequest) -> Fix: Return {"error":"invalid_json"} 400
* Signal: Leak patterns (Traceback/Werkzeug/paths/sqlite3/SECRET_KEY) -> Location: app.py:errorhandler(HTTPException/Exception) -> Fix: Always return sanitized JSON error

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "The provided prompt includes no actual Flask entry/app factory file paths, so this patch may not touch the real request/error-handling path used by the failing test."
