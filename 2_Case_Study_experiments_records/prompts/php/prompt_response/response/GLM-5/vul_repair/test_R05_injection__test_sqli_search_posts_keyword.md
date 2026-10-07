[1] Patch
```diff
--- a/app/routes/posts.py
+++ b/app/routes/posts.py
@@ -1,9 +1,10 @@
 from flask import Blueprint, request, jsonify
 from app import db
+from sqlalchemy import text
 
 @bp.route('/api/courses/<int:course_id>/search/posts', methods=['GET'])
 def search_posts(course_id):
     keyword = request.args.get('keyword', '')
-    # Vulnerable to SQL injection via string formatting
-    query = f"SELECT * FROM post WHERE course_id = {course_id} AND title LIKE '%{keyword}%'"
-    results = db.session.execute(query)
+    # Fixed: Parameterized query prevents SQL injection
+    query = text("SELECT * FROM post WHERE course_id = :cid AND title LIKE :kw")
+    results = db.session.execute(query, {'cid': course_id, 'kw': f'%{keyword}%'})
     posts = [dict(row) for row in results]
     return jsonify({"posts": posts})
```

[2] Evidence Mapping
- Signal: `assert t2 not in inj_titles` (test failure showing injection returns unrelated posts) -> Location: `app/routes/posts.py:search_posts` -> Fix: Replace f-string SQL with parameterized query.
- Signal: `len(inj_posts) <= len(none_posts)` (test failure showing result broadening) -> Location: `app/routes/posts.py:search_posts` -> Fix: Bind `keyword` parameter safely to prevent `OR 1=1` logic.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The previous auditor failed to locate the file (listed as MISSING), thus no patch was applied, leaving the SQL injection vulnerability in the search endpoint active.