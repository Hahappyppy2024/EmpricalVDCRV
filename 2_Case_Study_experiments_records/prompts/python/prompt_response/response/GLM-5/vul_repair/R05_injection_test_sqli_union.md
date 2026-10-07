[1] Patch
```diff
--- a/controllers/post_controller.py
+++ b/controllers/post_controller.py
@@ -12,8 +12,8 @@
 def search_posts(course_id):
     keyword = request.args.get('keyword', '')
 
-    # Vulnerable: String concatenation allows SQL injection
-    query = f"SELECT * FROM posts WHERE course_id = {course_id} AND content LIKE '%{keyword}%'"
-    results = db.execute(query)
+    # Fixed: Use parameterized query to prevent injection
+    query = "SELECT * FROM posts WHERE course_id = %s AND content LIKE %s"
+    results = db.execute(query, (course_id, f'%{keyword}%'))
 
     return jsonify(results)
```

[2] Evidence Mapping
- Signal: Failing test `test_api_sqli_search_posts_union_does_not_leak_user_table` -> Location: `controllers/post_controller.py:search_posts` -> Fix: Replaced string concatenation with parameterized query.
- Signal: Payload `test' UNION SELECT * FROM user--` -> Location: SQL query construction -> Fix: Input `keyword` is now passed as a data parameter, neutralizing the UNION attack.
- Signal: `assert b"password_hash" not in injected_bytes` -> Location: Database execution layer -> Fix: Malicious SQL logic is no longer executed, preventing data leakage.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The vulnerability exists because the application currently constructs SQL queries via unsafe string formatting, failing to sanitize the 'keyword' input.