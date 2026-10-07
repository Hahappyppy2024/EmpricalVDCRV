[1] Patch

```diff
--- a/src/db.js
+++ b/src/db.js
@@ -1,3 +1,4 @@
 // Assuming standard DB setup
 const db = require('better-sqlite3')('app.db'); 
+db.exec("CREATE TABLE IF NOT EXISTS invite_tokens (invite_id INTEGER PRIMARY KEY, course_id INTEGER, token_hash TEXT UNIQUE, used INTEGER DEFAULT 0)");
 module.exports = { getDb: () => db };
 
--- a/src/app.js
+++ b/src/app.js
@@ -1,2 +1,4 @@
 const app = require('express')();
 app.use(require('body-parser').json());
+app.use('/api/courses/:course_id/invites', require('./routes/invites'));
+app.use('/api/join', require('./routes/join'));
 module.exports = app;
 
--- /dev/null
+++ b/src/routes/invites.js
@@ -0,0 +1,15 @@
+const express = require('express');
+const router = express.Router({ mergeParams: true });
+const crypto = require('crypto');
+const { getDb } = require('../db');
+
+router.post('/', (req, res) => {
+  const token = crypto.randomBytes(16).toString('hex');
+  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
+  const db = getDb();
+  db.prepare('INSERT INTO invite_tokens (course_id, token_hash) VALUES (?, ?)').run(req.params.course_id, tokenHash);
+  res.status(201).json({ invite_link: `http://localhost/api/join?token=${token}` });
+});
+
+module.exports = router;
+
--- /dev/null
+++ b/src/routes/join.js
@@ -0,0 +1,18 @@
+const express = require('express');
+const router = express.Router();
+const crypto = require('crypto');
+const { getDb } = require('../db');
+
+router.post('/', (req, res) => {
+  const { token } = req.body;
+  if (!token) return res.status(400).json({ error: 'Token required' });
+
+  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
+  const db = getDb();
+  const record = db.prepare('SELECT * FROM invite_tokens WHERE token_hash = ?').get(tokenHash);
+
+  if (!record || record.used) return res.status(403).json({ error: 'Invalid or used token' });
+
+  db.prepare('UPDATE invite_tokens SET used = 1 WHERE invite_id = ?').run(record.invite_id);
+  res.status(200).json({ message: 'Joined successfully' });
+});
+
+module.exports = router;
```

[2] Evidence Mapping
- Signal: Test fails on plaintext token check -> Location: routes/invites.js:handler -> Fix: Hash token to SHA256 before storage.
- Signal: Test fails on second join (reuse) -> Location: routes/join.js:handler -> Fix: Check 'used' flag and update to 1 upon successful redemption.
- Signal: Test checks for 'invite_tokens' table -> Location: src/db.js:initialization -> Fix: Create table with token_hash and used columns.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: "The test fails because the required route handlers and database schema were completely missing, preventing the security properties (hashing and single-use) from being enforced."