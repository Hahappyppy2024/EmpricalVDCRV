[1] Patch

```diff
diff --git a/app.py b/app.py
new file mode 100644
index 0000000..4b2b6d1
--- /dev/null
+++ b/app.py
@@ -0,0 +1,235 @@
+import sqlite3
+from functools import wraps
+
+from flask import Flask, jsonify, request, session, g
+from werkzeug.exceptions import BadRequest, UnsupportedMediaType
+
+
+def create_app(db_path="app.db"):
+    app = Flask(__name__)
+    # NOTE: assume existing deployment sets SECRET_KEY via env/config
+    app.config.setdefault("SECRET_KEY", "dev-only-change-me")
+    app.config["DB_PATH"] = db_path
+
+    # ----------------------------
+    # DB helpers
+    # ----------------------------
+    def get_db():
+        if "db" not in g:
+            g.db = sqlite3.connect(app.config["DB_PATH"])
+            g.db.row_factory = sqlite3.Row
+        return g.db
+
+    @app.teardown_appcontext
+    def _close_db(_exc):
+        db = g.pop("db", None)
+        if db is not None:
+            db.close()
+
+    # ----------------------------
+    # Auth helpers (session-based)
+    # ----------------------------
+    def require_login(fn):
+        @wraps(fn)
+        def wrapper(*args, **kwargs):
+            uid = session.get("user_id")
+            if not uid:
+                return jsonify({"error": "unauthorized"}), 401
+            g.user_id = int(uid)
+            g.role = session.get("role")  # optional
+            return fn(*args, **kwargs)
+        return wrapper
+
+    def require_admin(fn):
+        @wraps(fn)
+        def wrapper(*args, **kwargs):
+            uid = session.get("user_id")
+            role = session.get("role")
+            if not uid:
+                return jsonify({"error": "unauthorized"}), 401
+            if role != "admin":
+                return jsonify({"error": "forbidden"}), 403
+            g.user_id = int(uid)
+            g.role = role
+            return fn(*args, **kwargs)
+        return wrapper
+
+    # ----------------------------
+    # Safe JSON parsing (no leaks)
+    # ----------------------------
+    def safe_json():
+        """
+        Parse JSON strictly and return (data, error_response).
+        Never include exception messages/tracebacks in responses.
+        """
+        # Enforce JSON content type for JSON endpoints
+        ct = (request.headers.get("Content-Type") or "").lower()
+        if "application/json" not in ct:
+            return None, (jsonify({"error": "unsupported_media_type"}), 415)
+        try:
+            data = request.get_json(force=False, silent=False)
+        except BadRequest:
+            return None, (jsonify({"error": "malformed_json"}), 400)
+        if data is None:
+            return None, (jsonify({"error": "malformed_json"}), 400)
+        return data, None
+
+    @app.errorhandler(BadRequest)
+    def _bad_request(_e):
+        # Robust: no stack trace/file path leakage
+        return jsonify({"error": "bad_request"}), 400
+
+    @app.errorhandler(UnsupportedMediaType)
+    def _unsupported(_e):
+        return jsonify({"error": "unsupported_media_type"}), 415
+
+    # ----------------------------
+    # Minimal API used by tests
+    # ----------------------------
+    @app.post("/api/courses")
+    @require_login
+    def api_create_course():
+        # This endpoint is used by the malformed-JSON test as a "common JSON endpoint"
+        data, err = safe_json()
+        if err:
+            return err
+        title = (data.get("title") or "").strip()
+        if not title:
+            return jsonify({"error": "validation"}), 422
+        db = get_db()
+        cur = db.execute("INSERT INTO courses(title) VALUES (?)", (title,))
+        db.commit()
+        return jsonify({"course_id": cur.lastrowid, "title": title}), 201
+
+    # --- Submission read endpoints (admin) ---
+    @app.get("/api/submissions/<int:submission_id>")
+    @require_admin
+    def api_get_submission_admin(submission_id: int):
+        db = get_db()
+        row = db.execute(
+            "SELECT submission_id, course_id, assignment_id, user_id, content_text "
+            "FROM submissions WHERE submission_id=?",
+            (submission_id,),
+        ).fetchone()
+        if not row:
+            return jsonify({"error": "not_found"}), 404
+        return jsonify(
+            {
+                "submission": {
+                    "submission_id": row["submission_id"],
+                    "course_id": row["course_id"],
+                    "assignment_id": row["assignment_id"],
+                    "user_id": row["user_id"],
+                    "content_text": row["content_text"],
+                }
+            }
+        ), 200
+
+    @app.get(
+        "/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>"
+    )
+    @require_admin
+    def api_get_submission_admin_scoped(course_id: int, assignment_id: int, submission_id: int):
+        db = get_db()
+        row = db.execute(
+            "SELECT submission_id, course_id, assignment_id, user_id, content_text "
+            "FROM submissions WHERE submission_id=? AND course_id=? AND assignment_id=?",
+            (submission_id, course_id, assignment_id),
+        ).fetchone()
+        if not row:
+            return jsonify({"error": "not_found"}), 404
+        return jsonify(
+            {
+                "submission": {
+                    "submission_id": row["submission_id"],
+                    "course_id": row["course_id"],
+                    "assignment_id": row["assignment_id"],
+                    "user_id": row["user_id"],
+                    "content_text": row["content_text"],
+                }
+            }
+        ), 200
+
+    # --- Submission update endpoint (student, owner-only) ---
+    @app.put(
+        "/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>"
+    )
+    @require_login
+    def api_update_submission(course_id: int, assignment_id: int, submission_id: int):
+        data, err = safe_json()
+        if err:
+            return err
+
+        new_text = data.get("content_text")
+        if new_text is None:
+            # Don't allow 400 to mask auth; use 422 for missing field
+            return jsonify({"error": "validation"}), 422
+
+        db = get_db()
+        row = db.execute(
+            "SELECT submission_id, user_id FROM submissions "
+            "WHERE submission_id=? AND course_id=? AND assignment_id=?",
+            (submission_id, course_id, assignment_id),
+        ).fetchone()
+        if not row:
+            return jsonify({"error": "not_found"}), 404
+
+        owner_id = int(row["user_id"])
+        if owner_id != int(g.user_id):
+            # Reject and ensure no update occurs (IDOR prevention)
+            return jsonify({"error": "forbidden"}), 403
+
+        # Defense-in-depth: owner constraint in UPDATE
+        cur = db.execute(
+            "UPDATE submissions SET content_text=? "
+            "WHERE submission_id=? AND user_id=? AND course_id=? AND assignment_id=?",
+            (new_text, submission_id, int(g.user_id), course_id, assignment_id),
+        )
+        db.commit()
+        if cur.rowcount != 1:
+            # If affected rows mismatch, treat as forbidden/no-op (no leak)
+            return jsonify({"error": "forbidden"}), 403
+
+        return jsonify({"ok": True, "submission_id": submission_id}), 200
+
+    return app
diff --git a/wsgi.py b/wsgi.py
new file mode 100644
index 0000000..2a9b2b1
--- /dev/null
+++ b/wsgi.py
@@ -0,0 +1,6 @@
+from app import create_app
+
+app = create_app()
+
+if __name__ == "__main__":
+    app.run()
```

[2] Evidence Mapping

* Signal: malformed JSON POST `/api/courses` must return 400/415/422 -> Location: `app.py:api_create_course/safe_json` -> Fix: Reject bad JSON, constant error body
* Signal: forbid IDOR on submission update -> Location: `app.py:api_update_submission` -> Fix: Check owner_id == session user_id
* Signal: prevent side-effects on unauthorized update -> Location: `app.py:api_update_submission` -> Fix: Return 403 before UPDATE executes
* Signal: admin must read submission content to verify no-change -> Location: `app.py:api_get_submission_admin(_scoped)` -> Fix: Provide stable JSON with content_text

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "The patch assumes Flask app structure/files (app.py/wsgi.py) that may not match your current codebase layout."
