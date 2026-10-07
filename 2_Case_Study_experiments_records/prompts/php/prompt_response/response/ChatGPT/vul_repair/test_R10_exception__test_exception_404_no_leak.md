[1] Patch

```diff
diff --git a/app.py b/app.py
index 1111111..2222222 100644
--- a/app.py
+++ b/app.py
@@ -1,9 +1,55 @@
-from flask import Flask
+from flask import Flask, jsonify, request
+from werkzeug.exceptions import BadRequest, HTTPException
 
 def create_app():
     app = Flask(__name__)
 
+    # Ensure we never return Werkzeug debug pages / tracebacks in API responses
+    app.config.setdefault("DEBUG", False)
+    app.config.setdefault("TESTING", False)
+    app.config.setdefault("PROPAGATE_EXCEPTIONS", False)
+
+    def _api_path():
+        try:
+            return request.path.startswith("/api/")
+        except Exception:
+            return False
+
+    @app.errorhandler(BadRequest)
+    def _handle_bad_request(e):
+        # Malformed JSON (and similar) should be a controlled 4xx with no leak
+        if _api_path():
+            return jsonify({"error": "bad_request"}), 400
+        return jsonify({"error": "bad_request"}), 400
+
+    @app.errorhandler(HTTPException)
+    def _handle_http_exception(e):
+        # Sanitize all HTTP errors for /api/*
+        if _api_path():
+            code = e.code or 500
+            if code in (400, 415, 422):
+                return jsonify({"error": "bad_request"}), code
+            if code == 404:
+                return jsonify({"error": "not_found"}), 404
+            if code == 405:
+                return jsonify({"error": "method_not_allowed"}), 405
+            return jsonify({"error": "http_error"}), code
+        return e
+
+    @app.errorhandler(Exception)
+    def _handle_exception(e):
+        # Never leak stack traces, file paths, DB errors, or secrets
+        if _api_path():
+            return jsonify({"error": "internal_error"}), 500
+        return jsonify({"error": "internal_error"}), 500
+
     return app
diff --git a/routes/uploads.py b/routes/uploads.py
index 3333333..4444444 100644
--- a/routes/uploads.py
+++ b/routes/uploads.py
@@ -1,8 +1,15 @@
-from flask import Blueprint, send_file
+from flask import Blueprint, send_file
 
 bp = Blueprint("uploads", __name__)
 
 @bp.get("/api/courses/<int:course_id>/uploads/<int:upload_id>/download")
 def download_upload(course_id, upload_id):
-    return send_file(_resolve_upload_path(course_id, upload_id))
+    # Force a safe download response (no MIME sniffing)
+    resp = send_file(
+        _resolve_upload_path(course_id, upload_id),
+        mimetype="application/octet-stream",
+        as_attachment=True,
+    )
+    resp.headers["Content-Type"] = "application/octet-stream"
+    resp.headers["X-Content-Type-Options"] = "nosniff"
+    return resp
```

[2] Evidence Mapping

* Signal: download missing `X-Content-Type-Options` -> Location: routes/uploads.py:download_upload -> Fix: add `nosniff` header
* Signal: download must be octet-stream -> Location: routes/uploads.py:download_upload -> Fix: force `application/octet-stream`
* Signal: malformed JSON must return 4xx -> Location: app.py:errorhandler(BadRequest) -> Fix: return 400 sanitized JSON
* Signal: no stacktrace/path/DB leak -> Location: app.py:errorhandler(Exception/HTTPException) -> Fix: sanitize /api errors

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "No code snippets were provided, so file/route names may not match the real execution path."
