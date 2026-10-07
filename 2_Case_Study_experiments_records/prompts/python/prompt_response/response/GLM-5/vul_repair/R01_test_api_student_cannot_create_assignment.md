[1] Patch
```diff
--- a/app/api.py
+++ b/app/api.py
@@ -1,10 +1,17 @@
 from flask import Blueprint, request, jsonify
+from flask_login import current_user
-from .models import Assignment, Post
+from .models import Assignment, Post, CourseMembership, db
 
 bp = Blueprint('api', __name__, url_prefix='/api')
 
 @bp.route('/courses/<int:course_id>/assignments', methods=['POST'])
 def create_assignment(course_id):
     data = request.get_json()
+    if not current_user.is_authenticated:
+        return jsonify({"error": "Authentication required"}), 401
+    membership = CourseMembership.query.filter_by(course_id=course_id, user_id=current_user.id).first()
+    if not membership or membership.role == 'student':
+        return jsonify({"error": "Insufficient permissions"}), 403
+
     assignment = Assignment(title=data['title'], description=data.get('description'), course_id=course_id)
     db.session.add(assignment)
     db.session.commit()
@@ -12,6 +19,12 @@ def create_assignment(course_id):
 
 @bp.route('/courses/<int:course_id>/posts', methods=['GET'])
 def list_posts(course_id):
+    if not current_user.is_authenticated:
+        return jsonify({"error": "Authentication required"}), 401
+    membership = CourseMembership.query.filter_by(course_id=course_id, user_id=current_user.id).first()
+    if not membership:
+        return jsonify({"error": "Access denied"}), 403
+
     posts = Post.query.filter_by(course_id=course_id).all()
     return jsonify([p.to_dict() for p in posts])
```

[2] Evidence Mapping
- Signal: Failing test `test_api_student_cannot_create_assignment` -> Location: `app/api.py:create_assignment` -> Fix: Add role check to deny 'student' creating assignments (return 403).
- Signal: Failing test `test_api_non_member_cannot_list_course_posts` -> Location: `app/api.py:list_posts` -> Fix: Add membership check to deny non-members listing posts (return 403).
- Signal: Test allows 401/403 -> Location: `app/api.py` -> Fix: Enforce authentication check (401) prior to membership check (403) for semantic correctness.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The RootCause indicates that authorization checks were missing entirely from the API routes, requiring the implementation of role and membership verification logic where none existed previously.