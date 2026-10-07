[1] Patch
```diff
--- a/routes/auth.py
+++ b/routes/auth.py
@@ -5,6 +5,9 @@ def login():
     # ... existing credential validation logic ...
     if user and check_password_hash(user.password, password):
+        # Security Fix: Regenerate session ID to prevent session fixation
+        session.clear()
+        # The new session ID is generated automatically by Flask upon modification
         session['user_id'] = user.id
         return jsonify({"status": "success"})
     return jsonify({"status": "fail"}), 401
```

[2] Evidence Mapping
- Signal: `assert sid_after != sid_before` failure in `test_login_regenerates_session_id` -> Location: `routes/auth.py:login` -> Fix: Insert `session.clear()` call immediately after credential verification.
- Signal: Test hint "Mitigation: call session_regenerate_id(true)" -> Location: `routes/auth.py:login` -> Fix: Use Flask's `session.clear()` or `session.regenerate()` equivalent to rotate ID.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The vulnerability exists because the login handler continues to use the existing session identifier instead of rotating it, and no prior fix was attempted in the provided context.