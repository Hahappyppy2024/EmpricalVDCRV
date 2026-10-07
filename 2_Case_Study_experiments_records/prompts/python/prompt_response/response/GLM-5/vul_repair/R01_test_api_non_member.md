[1] Patch
```diff
--- a/routes/courses.py
+++ b/routes/courses.py
@@ -1,5 +1,6 @@
 from flask import Blueprint, jsonify, request, abort
 from flask_login import current_user
+from app.models import Course, Membership
 
 bp = Blueprint('courses', __name__)
 
@@ -15,6 +16,9 @@ def create_assignment(course_id):
     """
     Create a new assignment for a course.
     """
+    # Security Fix: Enforce role-based access control
+    if not current_user.is_authenticated or current_user.role != 'teacher':
+        abort(403)
 
     # ... existing assignment creation logic ...
 
@@ -40,6 +44,9 @@ def list_posts(course_id):
     """
     Retrieve posts for a specific course.
     """
+    # Security Fix: Enforce membership check
+    if not current_user.is_authenticated or not Membership.query.filter_by(user_id=current_user.id, course_id=course_id).first():
+        abort(403)
 
     # ... existing post retrieval logic ...
```

[2] Evidence Mapping
- Signal: Student successfully creates assignment (expected 403) -> Location: `routes/courses.py:create_assignment` -> Fix: Add role check for 'teacher'.
- Signal: Non-member lists posts (expected 403) -> Location: `routes/courses.py:list_posts` -> Fix: Add membership validation check.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The application code currently lacks the necessary authorization checks in the specified routes, resulting in unauthorized actions succeeding.