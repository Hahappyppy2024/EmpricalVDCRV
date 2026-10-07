[1] Patch
```diff
--- a/controllers/post_controller.py
+++ b/controllers/post_controller.py
@@ -1,7 +1,9 @@
 from flask import request
+from sqlalchemy import text
 from models import db
 
 def get_posts(course_id):
     keyword = request.args.get('keyword', '')
-    query = f"SELECT * FROM posts WHERE content LIKE '%{keyword}%'"
-    results = db.session.execute(query)
+    # Fix: Use parameterized query to prevent SQL injection
+    query = text("SELECT * FROM posts WHERE content LIKE :keyword")
+    results = db.session.execute(query, {'keyword': f'%{keyword}%'})
     return results
```

[2] Evidence Mapping
- Signal: Test `test_api_sqli_search_posts_tautology_does_not_expand_results` fails on `len(injected) <= len(baseline)` -> Location: `controllers/post_controller.py:get_posts` -> Fix: Replace string concatenation with parameterized query.
- Signal: RootCause describes `f"SELECT ... '%{keyword}%'")` -> Location: `controllers/post_controller.py` -> Fix: Use bound parameters (`:keyword`) to treat input as data.
- Signal: Test checks for `password_hash` and `sqlite_master` leakage -> Location: Database query execution -> Fix: Parameterization prevents execution of injected Union/Select sub-queries.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The application currently uses string formatting to build the SQL query, which allows the injected tautology to alter the query logic and expand the result set.