[1] Patch

```diff
diff --git a/src/app.js b/src/app.js
index 1111111..2222222 100644
--- a/src/app.js
+++ b/src/app.js
@@ -1,20 +1,54 @@
 const express = require('express');
 
+// If your project already mounts routes elsewhere, keep that logic;
+// this patch only hardens JSON parse errors and sanitizes responses.
+
 function makeApp() {
   const app = express();
 
-  app.use(express.json());
+  // Parse JSON bodies; malformed JSON will raise a SyntaxError.
+  app.use(express.json({ strict: true, type: ['application/json', '*/json'] }));
 
   // ... existing middleware / routes mounting ...
+  // Example (keep your existing):
+  // app.use('/api', require('./routes/api'));
 
+  // Global error handler (must be AFTER routes and body parsers)
+  app.use((err, req, res, next) => {
+    // Body-parser / express.json malformed JSON
+    // Common signals: SyntaxError + type === 'entity.parse.failed'
+    const isJsonParseError =
+      err &&
+      (err instanceof SyntaxError || err.name === 'SyntaxError') &&
+      (err.type === 'entity.parse.failed' || /JSON/i.test(err.message || ''));
+
+    if (isJsonParseError) {
+      // Controlled, non-leaky response (no stack, no file paths, no exception details)
+      return res.status(400).json({ error: 'Invalid JSON' });
+    }
+
+    // If you have an existing error handler, merge logic there instead.
+    return next(err);
+  });
+
   return app;
 }
 
 module.exports = { makeApp };
```

[2] Evidence Mapping

* Signal: malformed JSON POST `/api/courses` -> Location: `src/app.js:express.json` -> Fix: Catch parse error, return 400 JSON
* Signal: body must not match `Traceback|stack|at\s|...|SyntaxError` -> Location: `src/app.js:error middleware` -> Fix: Sanitize body, no stack/message
* Signal: test accepts 400/415 -> Location: `src/app.js:error middleware` -> Fix: Prefer 400 for invalid JSON

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Core files are marked MISSING, so the guessed app entrypoint may not match the test’s actual app factory."
