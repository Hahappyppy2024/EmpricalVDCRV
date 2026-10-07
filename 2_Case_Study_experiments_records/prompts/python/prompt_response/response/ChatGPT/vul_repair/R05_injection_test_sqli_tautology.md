[1] Patch

```diff
diff --git a/app/routes/api.py b/app/routes/api.py
new file mode 100644
index 0000000..b3d3e6a
--- /dev/null
+++ b/app/routes/api.py
@@ -0,0 +1,92 @@
+from flask import Blueprint, request, jsonify
+import sqlite3
+
+from app.db import get_db
+from app.auth import require_auth  # assumes existing auth guard used by other /api routes
+
+api = Blueprint("api", __name__, url_prefix="/api")
+
+
+@api.get("/courses/<int:course_id>/posts")
+@require_auth
+def list_or_search_posts(course_id: int):
+    """
+    SQLi-resistant keyword search:
+    - parameterize LIKE
+    - select explicit safe columns (no SELECT *)
+    - sanitize DB errors (no schema/SQL leakage)
+    """
+    keyword = request.args.get("keyword", "")
+    if keyword is None:
+        keyword = ""
+    if not isinstance(keyword, str):
+        return jsonify({"error": "invalid keyword"}), 400
+    # simple guardrail (keeps behavior predictable and avoids pathological queries)
+    keyword = keyword.strip()
+    if len(keyword) > 200:
+        return jsonify({"error": "keyword too long"}), 400
+
+    db = get_db()
+    try:
+        if keyword:
+            like = f"%{keyword}%"
+            rows = db.execute(
+                """
+                SELECT
+                  p.id AS post_id,
+                  p.title AS title,
+                  p.body AS body,
+                  p.created_at AS created_at,
+                  u.display_name AS author_display_name
+                FROM posts p
+                LEFT JOIN users u ON u.id = p.author_id
+                WHERE p.course_id = ?
+                  AND (p.title LIKE ? ESCAPE '\\' OR p.body LIKE ? ESCAPE '\\')
+                ORDER BY p.created_at DESC
+                """,
+                (course_id, like, like),
+            ).fetchall()
+        else:
+            rows = db.execute(
+                """
+                SELECT
+                  p.id AS post_id,
+                  p.title AS title,
+                  p.body AS body,
+                  p.created_at AS created_at,
+                  u.display_name AS author_display_name
+                FROM posts p
+                LEFT JOIN users u ON u.id = p.author_id
+                WHERE p.course_id = ?
+                ORDER BY p.created_at DESC
+                """,
+                (course_id,),
+            ).fetchall()
+    except sqlite3.Error:
+        # Do not leak SQL text, schema strings (e.g., sqlite_master), or stack traces
+        return jsonify({"error": "bad request"}), 400
+
+    # Ensure JSON list of safe dicts (never include password_hash or other auth fields)
+    out = []
+    for r in rows:
+        try:
+            out.append(
+                {
+                    "post_id": r["post_id"],
+                    "title": r["title"],
+                    "body": r["body"],
+                    "created_at": r["created_at"],
+                    "author_display_name": r["author_display_name"],
+                }
+            )
+        except Exception:
+            # fallback for tuple rows
+            out.append(
+                {
+                    "post_id": r[0],
+                    "title": r[1],
+                    "body": r[2],
+                    "created_at": r[3],
+                    "author_display_name": r[4],
+                }
+            )
+    return jsonify(out), 200
diff --git a/app/__init__.py b/app/__init__.py
new file mode 100644
index 0000000..a2a4b8c
--- /dev/null
+++ b/app/__init__.py
@@ -0,0 +1,18 @@
+from flask import Flask
+
+def create_app():
+    app = Flask(__name__)
+
+    # Register API blueprint(s)
+    from app.routes.api import api as api_bp
+    app.register_blueprint(api_bp)
+
+    return app
diff --git a/app/db.py b/app/db.py
new file mode 100644
index 0000000..0f6c0f1
--- /dev/null
+++ b/app/db.py
@@ -0,0 +1,26 @@
+import sqlite3
+from flask import g
+
+def _connect():
+    # assumes sqlite file path is configured elsewhere; keep minimal here
+    conn = sqlite3.connect("app.db")
+    conn.row_factory = sqlite3.Row
+    return conn
+
+def get_db():
+    db = getattr(g, "_db", None)
+    if db is None:
+        g._db = _connect()
+        db = g._db
+    return db
diff --git a/app/auth.py b/app/auth.py
new file mode 100644
index 0000000..8ff0c3a
--- /dev/null
+++ b/app/auth.py
@@ -0,0 +1,20 @@
+from functools import wraps
+from flask import session, jsonify
+
+def require_auth(fn):
+    @wraps(fn)
+    def wrapper(*args, **kwargs):
+        # Minimal auth check; assumes session-based login used by tests
+        if not session.get("user_id"):
+            return jsonify({"error": "unauthorized"}), 401
+        return fn(*args, **kwargs)
+    return wrapper
```

[2] Evidence Mapping

* Signal: `len(injected) <= len(baseline)` oracle -> Location: `/api/courses/<id>/posts` -> Fix: Parameterize LIKE; no SQL concatenation
* Signal: payload `"' OR 1=1 --"` -> Location: posts search SQL -> Fix: Bind `%keyword%` as query parameter
* Signal: `password_hash` must not appear -> Location: JSON serialization -> Fix: Explicit safe fields; avoid `SELECT *`
* Signal: `sqlite_master` must not appear -> Location: DB exception path -> Fix: Catch sqlite errors; return generic 400

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "The prompt provides no existing file/code context, so the patch may not touch the real route/query implementation used by the test."
