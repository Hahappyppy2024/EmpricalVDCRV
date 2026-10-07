[1] Patch
```diff
--- a/routes/submission_routes.py
+++ b/routes/submission_routes.py
@@ -12,6 +12,11 @@ def update_submission(submission_id):
     if not submission:
         return jsonify({"error": "Submission not found"}), 404
 
+    # Security Fix: IDOR Prevention
+    if submission.user_id != current_user.id:
+        return jsonify({"error": "Forbidden: You do not own this submission"}), 403
+
     data = request.get_json()
     if not data:
         return jsonify({"error": "Invalid JSON data"}), 400
```

[2] Evidence Mapping
- Signal: Failing test `test_student_cannot_update_other_students_submission_strict` allows s2 to update s1 -> Location: `routes/submission_routes.py:update_submission` -> Fix: Add ownership check (`submission.user_id == current_user.id`).
- Signal: Test expects 401/403/404 for unauthorized access -> Location: `routes/submission_routes.py` -> Fix: Return 403 Forbidden when ownership check fails.

[3] FailureModeGuess
failure_mode_guess: "N/A"
failure_mode_guess_reason: "This is the initial repair patch based on the provided root cause analysis, addressing the missing authorization check."