### [1] Patch

```diff
*** Begin Patch
*** Update File: app.py
@@
-from flask import Flask
+from flask import Flask, request, jsonify
+from werkzeug.exceptions import BadRequest
 
 app = Flask(__name__)
 
+@app.after_request
+def _security_headers(resp):
+    # Prevent MIME sniffing (required by download hardening test).
+    if resp is not None:
+        resp.headers.setdefault("X-Content-Type-Options", "nosniff")
+    return resp
+
+@app.errorhandler(BadRequest)
+def _handle_bad_request(e):
+    # Return controlled 4xx for malformed JSON without leaking internals.
+    return jsonify({"error": "bad_request"}), 400
+
+@app.errorhandler(Exception)
+def _handle_unexpected(e):
+    # Avoid leaking stack traces / file paths in error bodies.
+    # If it's an HTTP exception, keep its status code; otherwise 500.
+    code = getattr(e, "code", 500)
+    return jsonify({"error": "internal_error"}), code
+
*** End Patch
```

### [2] Evidence Mapping

* Signal: download test expects `X-Content-Type-Options: nosniff` -> Location: app global response headers -> Fix: set default nosniff header
* Signal: malformed JSON to `/api/courses` must be 4xx -> Location: app error handling -> Fix: BadRequest handler returns 400 JSON
* Signal: no stack/file/DB leak in body -> Location: app exception handler -> Fix: replace body with generic JSON

### [3] FailureModeGuess

failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Patch assumes Flask `app.py` exists and owns global hooks; if routing/response code lives elsewhere, headers/errors won’t apply."
