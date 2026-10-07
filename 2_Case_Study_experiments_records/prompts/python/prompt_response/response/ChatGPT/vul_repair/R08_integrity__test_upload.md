[1] Patch

```diff
diff --git a/src/app.py b/src/app.py
index 1111111..2222222 100644
--- a/src/app.py
+++ b/src/app.py
@@ -1,12 +1,30 @@
+import os
 from flask import Flask
 
 def create_app():
     app = Flask(__name__)
 
+    # Ensure a real upload folder exists for API upload endpoints/tests
+    # Keep this as an absolute path, but store DB storage_path as *relative*.
+    upload_root = app.config.get("UPLOAD_FOLDER")
+    if not upload_root:
+        upload_root = os.path.join(app.instance_path, "uploads")
+        app.config["UPLOAD_FOLDER"] = upload_root
+    os.makedirs(app.config["UPLOAD_FOLDER"], exist_ok=True)
+
     # register blueprints
     from .routes.api import api_bp
     app.register_blueprint(api_bp, url_prefix="/api")
 
     return app
diff --git a/src/routes/api.py b/src/routes/api.py
index 3333333..4444444 100644
--- a/src/routes/api.py
+++ b/src/routes/api.py
@@ -1,8 +1,12 @@
 from flask import Blueprint
 
 api_bp = Blueprint("api", __name__)
 
+# ... existing imports/routes ...
+
+from ..controllers.upload_controller import create_course_upload
+
+# Uploads (course-scoped)
+api_bp.add_url_rule("/courses/<int:course_id>/uploads", view_func=create_course_upload, methods=["POST"])
diff --git a/src/controllers/upload_controller.py b/src/controllers/upload_controller.py
new file mode 100644
index 0000000..5555555
--- /dev/null
+++ b/src/controllers/upload_controller.py
@@ -0,0 +1,109 @@
+import os
+import secrets
+from flask import current_app, request, jsonify
+from werkzeug.utils import secure_filename
+
+from ..db import get_db
+
+
+def _safe_basename(filename: str) -> str:
+    """
+    Convert client-supplied filenames like '../../malicious.txt' -> 'malicious.txt'
+    and normalize to a safe filename token.
+    """
+    raw = filename or ""
+    # Drop any directory components (both POSIX and Windows separators)
+    base = os.path.basename(raw.replace("\\", "/"))
+    base = base.strip().lstrip(".")  # avoid hidden/tricky dotfiles
+    safe = secure_filename(base)
+    return safe or "upload.bin"
+
+
+def _safe_rel_storage_path(course_id: int, original_name: str) -> str:
+    """
+    Build a *relative* storage path that is never absolute and never contains '..'.
+    """
+    # Keep extension (if any) to preserve type hints; randomize name to avoid collisions
+    _, ext = os.path.splitext(original_name)
+    token = secrets.token_hex(16)
+    rel = os.path.join(str(course_id), f"{token}{ext}")
+    # Normalize to forward slashes for DB portability
+    rel = rel.replace("\\", "/")
+    # Enforce invariants required by the test
+    if os.path.isabs(rel) or ".." in rel.split("/"):
+        # Fallback to a very safe flat name
+        rel = f"{token}{ext}".lstrip("/").replace("\\", "/")
+    return rel
+
+
+def create_course_upload(course_id: int):
+    """
+    POST /api/courses/<course_id>/uploads
+    Expects multipart/form-data with field name 'file'.
+    Stores DB row in 'upload' table with:
+      - original_name: sanitized basename
+      - storage_path: safe relative path under UPLOAD_FOLDER
+    """
+    if "file" not in request.files:
+        return jsonify({"error": "missing file"}), 400
+    f = request.files["file"]
+    if not f or not getattr(f, "filename", ""):
+        return jsonify({"error": "empty filename"}), 400
+
+    original_name = _safe_basename(f.filename)
+    storage_path = _safe_rel_storage_path(course_id, original_name)
+
+    upload_root = current_app.config.get("UPLOAD_FOLDER")
+    if not upload_root:
+        # Defensive: app.py should set this, but keep endpoint robust
+        upload_root = os.path.join(current_app.instance_path, "uploads")
+        current_app.config["UPLOAD_FOLDER"] = upload_root
+    os.makedirs(upload_root, exist_ok=True)
+
+    full_path = os.path.join(upload_root, storage_path)
+    # Ensure directory exists for nested <course_id>/...
+    os.makedirs(os.path.dirname(full_path), exist_ok=True)
+
+    # Persist DB row first (so the record exists even if file save fails later)
+    db = get_db()
+    cur = db.execute(
+        "INSERT INTO upload (course_id, original_name, storage_path) VALUES (?, ?, ?)",
+        (course_id, original_name, storage_path),
+    )
+    db.commit()
+
+    # Save file on disk under UPLOAD_FOLDER / storage_path
+    f.save(full_path)
+
+    return jsonify({"upload_id": cur.lastrowid, "original_name": original_name, "storage_path": storage_path}), 201
diff --git a/src/db.py b/src/db.py
index 6666666..7777777 100644
--- a/src/db.py
+++ b/src/db.py
@@ -1,10 +1,23 @@
 import sqlite3
 from flask import g, current_app
 
 def get_db():
     if "db" not in g:
         g.db = sqlite3.connect(
             current_app.config["DATABASE"],
             detect_types=sqlite3.PARSE_DECLTYPES
         )
         g.db.row_factory = sqlite3.Row
     return g.db
+
+def init_db():
+    db = get_db()
+    # Ensure upload table exists with required columns used by security tests
+    db.execute(
+        """
+        CREATE TABLE IF NOT EXISTS upload (
+          upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
+          course_id INTEGER,
+          original_name TEXT NOT NULL,
+          storage_path TEXT NOT NULL
+        )
+        """
+    )
+    db.commit()
```

[2] Evidence Mapping

* Signal: filename "../../malicious.txt" -> Location: src/controllers/upload_controller.py:_safe_basename -> Fix: sanitize to basename + secure_filename
* Signal: assert ".." not in storage_path -> Location: src/controllers/upload_controller.py:_safe_rel_storage_path -> Fix: generate random safe relative path
* Signal: assert not os.path.isabs(storage_path) -> Location: src/controllers/upload_controller.py:_safe_rel_storage_path -> Fix: forbid absolute, normalize separators
* Signal: os.path.exists(UPLOAD_FOLDER/storage_path) -> Location: src/controllers/upload_controller.py:create_course_upload -> Fix: mkdirs + save under UPLOAD_FOLDER

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Project file layout/DB init hooks are unknown, so the new route/config/table may not be wired into the actual app factory used by tests."
