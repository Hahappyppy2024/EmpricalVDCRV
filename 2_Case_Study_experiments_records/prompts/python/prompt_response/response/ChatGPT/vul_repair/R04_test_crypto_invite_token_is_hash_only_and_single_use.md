[1] Patch

```diff
diff --git a/schema.sql b/schema.sql
index 1111111..2222222 100644
--- a/schema.sql
+++ b/schema.sql
@@ -1,3 +1,29 @@
+-- Invite tokens: hash-only + single-use + expiry
+CREATE TABLE IF NOT EXISTS invite_token (
+  invite_token_id     INTEGER PRIMARY KEY AUTOINCREMENT,
+  course_id           INTEGER NOT NULL,
+  role_in_course      TEXT NOT NULL,
+  token_hash          TEXT NOT NULL UNIQUE,
+  expires_at          TEXT NOT NULL,
+  used_at             TEXT NULL,
+  used_by_user_id     INTEGER NULL,
+  created_at          TEXT NOT NULL DEFAULT (datetime('now')),
+  FOREIGN KEY(course_id) REFERENCES course(course_id),
+  FOREIGN KEY(used_by_user_id) REFERENCES user(user_id)
+);
+
+CREATE INDEX IF NOT EXISTS idx_invite_token_hash ON invite_token(token_hash);
+CREATE INDEX IF NOT EXISTS idx_invite_token_course ON invite_token(course_id);
+
diff --git a/app.py b/app.py
index 3333333..4444444 100644
--- a/app.py
+++ b/app.py
@@ -1,10 +1,16 @@
 from flask import Flask, request, jsonify, redirect
+import hashlib
+import secrets
+from datetime import datetime, timedelta
 
 app = Flask(__name__)
 
+# NOTE: this file assumes an existing get_db() that returns a sqlite3 connection
+# with row_factory set (dict-like rows). If your project uses a different helper,
+# adapt get_db() usage only (logic below stays the same).
+
 def get_db():
     raise NotImplementedError
 
@@ -50,6 +56,118 @@ def require_login():
     raise NotImplementedError
 
+def _sha256_hex(s: str) -> str:
+    return hashlib.sha256(s.encode("utf-8")).hexdigest()
+
+def _iso(dt: datetime) -> str:
+    return dt.strftime("%Y-%m-%d %H:%M:%S")
+
+@app.post("/api/courses/<int:course_id>/invites")
+def api_create_invite(course_id: int):
+    """
+    Security property:
+      - Store hash-only token (64 hex SHA-256)
+      - Return plaintext token ONLY inside invite_link
+      - TTL enforced via expires_at
+    """
+    user = require_login()
+    body = request.get_json(silent=True) or {}
+    role = (body.get("role_in_course") or "").strip() or "student"
+    ttl_minutes = int(body.get("ttl_minutes") or 60)
+    if ttl_minutes <= 0:
+        return jsonify({"error": "bad_ttl"}), 400
+
+    # Create random token (plaintext returned once)
+    token = secrets.token_urlsafe(24)
+    token_hash = _sha256_hex(token)
+    expires_at = _iso(datetime.utcnow() + timedelta(minutes=ttl_minutes))
+
+    db = get_db()
+    # Store ONLY token_hash (never plaintext token)
+    db.execute(
+        """
+        INSERT INTO invite_token(course_id, role_in_course, token_hash, expires_at)
+        VALUES (?, ?, ?, ?)
+        """,
+        (course_id, role, token_hash, expires_at),
+    )
+    db.commit()
+
+    # Invite link includes plaintext token, which is not stored
+    invite_link = f"/join?token={token}"
+    return jsonify({"invite_link": invite_link}), 200
+
+
+@app.post("/api/join")
+def api_join_with_invite():
+    """
+    Single-use enforcement:
+      - Hash provided token and atomically 'consume' it (mark used_at/used_by_user_id)
+      - First use succeeds; any reuse is rejected (400/403)
+    """
+    user = require_login()
+    body = request.get_json(silent=True) or {}
+    token = (body.get("token") or "").strip()
+    if not token:
+        return jsonify({"error": "missing_token"}), 400
+
+    token_hash = _sha256_hex(token)
+    now = _iso(datetime.utcnow())
+
+    db = get_db()
+
+    # Atomically claim the token if it is valid, unexpired, and unused.
+    # This avoids TOCTOU and enforces single-use even under concurrency.
+    cur = db.execute(
+        """
+        UPDATE invite_token
+           SET used_at = ?, used_by_user_id = ?
+         WHERE token_hash = ?
+           AND used_at IS NULL
+           AND expires_at > ?
+        """,
+        (now, user["user_id"], token_hash, now),
+    )
+    if cur.rowcount != 1:
+        # Invalid / expired / already used
+        return jsonify({"error": "invalid_or_used_token"}), 403
+
+    # Fetch course/role for membership creation
+    row = db.execute(
+        "SELECT course_id, role_in_course FROM invite_token WHERE token_hash = ?",
+        (token_hash,),
+    ).fetchone()
+    if not row:
+        # Should not happen after successful UPDATE, but keep safe.
+        return jsonify({"error": "invalid_or_used_token"}), 403
+
+    course_id = row["course_id"]
+    role = row["role_in_course"]
+
+    # Create membership (idempotent if already member)
+    # If your table/columns differ, adjust ONLY this INSERT accordingly.
+    db.execute(
+        """
+        INSERT OR IGNORE INTO course_membership(course_id, user_id, role_in_course)
+        VALUES (?, ?, ?)
+        """,
+        (course_id, user["user_id"], role),
+    )
+    db.commit()
+    return jsonify({"joined": True, "course_id": course_id}), 200
+
 
 if __name__ == "__main__":
     app.run()
```

[2] Evidence Mapping

* Signal: DB asserts `token_hash` is 64-hex and not plaintext -> Location: `/api/courses/<course_id>/invites` -> Fix: store `sha256(token)` only
* Signal: reuse of same token must fail (400/403) -> Location: `POST /api/join` -> Fix: atomic consume via `UPDATE ... used_at IS NULL`
* Signal: test passes `ttl_minutes` and expects enforcement -> Location: invite_token schema + invite creation -> Fix: persist `expires_at`, check on consume

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Patch assumes Flask+SQLite helpers/table names that may differ since no code snippets were provided."
