[1] Patch
```diff
--- /dev/null
+++ b/routes/invite_routes.py
@@ -0,0 +1,28 @@
+import hashlib
+import secrets
+from flask import request, jsonify, g
+
+# Assuming blueprint registration in app.py: app.register_blueprint(invite_routes.bp)
+# or similar wiring.
+
+def create_invite(course_id):
+    # Authentication check is assumed to be handled by framework/middleware
+    # generating g.user
+    
+    data = request.get_json()
+    if not data:
+        return jsonify(error="Invalid request"), 400
+
+    # Generate secure random token
+    raw_token = secrets.token_urlsafe(32)
+    
+    # Store only the hash
+    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
+
+    role = data.get('role_in_course')
+    ttl = data.get('ttl_minutes')
+
+    cur = g.db.cursor()
+    cur.execute("INSERT INTO invite_token (course_id, token_hash, role_in_course, ttl_minutes) VALUES (?, ?, ?, ?)",
+                (course_id, token_hash, role, ttl))
+    g.db.commit()
+
+    return jsonify(invite_link=f"/join?token={raw_token}"), 200

--- /dev/null
+++ b/routes/join_routes.py
@@ -0,0 +1,30 @@
+import hashlib
+from flask import request, jsonify, g
+
+def join_course():
+    data = request.get_json()
+    if not data or 'token' not in data:
+        return jsonify(error="Token required"), 400
+
+    raw_token = data['token']
+    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
+
+    cur = g.db.cursor()
+    cur.execute("SELECT course_id, role_in_course FROM invite_token WHERE token_hash = ?", (token_hash,))
+    row = cur.fetchone()
+
+    if not row:
+        return jsonify(error="Invalid or expired token"), 403
+
+    # Enforce single-use by deleting the token
+    cur.execute("DELETE FROM invite_token WHERE token_hash = ?", (token_hash,))
+
+    # Add user to course
+    # Assuming row is a dict-like object or accessing by index
+    course_id = row['course_id'] if hasattr(row, 'keys') else row[0]
+    role = row['role_in_course'] if hasattr(row, 'keys') else row[1]
+
+    cur.execute("INSERT INTO course_membership (course_id, user_id, role) VALUES (?, ?, ?)",
+                (course_id, g.user.id, role))
+    g.db.commit()
+
+    return jsonify(joined=True), 200
```

[2] Evidence Mapping
- Signal: `assert token_hash != token` (plaintext storage failure) -> Location: `routes/invite_routes.py:create_invite` -> Fix: Hash token with SHA-256 before DB insertion.
- Signal: `assert rv.status_code in (400, 403)` (replay attack) -> Location: `routes/join_routes.py:join_course` -> Fix: Delete/validate token existence to ensure single use.
- Signal: `db_all(client, "SELECT token_hash FROM invite_token")` -> Location: `routes/invite_routes.py` -> Fix: Ensure DB column `token_hash` stores the hash, not plaintext.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The existing application logic was missing or implemented insecurely (storing plaintext, no invalidation), failing the security test's checks for confidentiality and single-use validity.