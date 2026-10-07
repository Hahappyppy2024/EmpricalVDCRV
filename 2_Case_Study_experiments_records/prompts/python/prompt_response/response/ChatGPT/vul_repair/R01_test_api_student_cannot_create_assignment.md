[1] Patch

```diff
diff --git a/src/routes/api.js b/src/routes/api.js
index 2b7c9c1..c1a4f6d 100644
--- a/src/routes/api.js
+++ b/src/routes/api.js
@@ -1,9 +1,74 @@
 const express = require('express');
 const router = express.Router();
 
 const auth = require('../middleware/auth');
+const db = require('../db');
 
@@
 const postCtrl = require('../controllers/postController');
@@
 const inviteController = require('../controllers/inviteController');
 const submissionZipCtrl=require('../controllers/submissionZipController');
 
+// ------------------------------------------------------------
+// R01 guards (auth + course membership/role enforcement)
+// ------------------------------------------------------------
+function _currentUserId(req) {
+  // Common session conventions
+  return (
+    req?.session?.userId ||
+    req?.session?.user_id ||
+    req?.user?.id ||
+    req?.user?.userId ||
+    null
+  );
+}
+
+function requireLogin(req, res, next) {
+  const uid = _currentUserId(req);
+  if (!uid) return res.status(401).json({ error: 'unauthenticated' });
+  req.currentUserId = uid;
+  return next();
+}
+
+async function _getMembership(courseId, userId) {
+  // Prefer a simple, canonical membership table shape.
+  // If your schema differs, adjust the table/columns here.
+  const q = `
+    SELECT role
+    FROM memberships
+    WHERE course_id = ? AND user_id = ?
+    LIMIT 1
+  `;
+  return await db.get(q, [courseId, userId]);
+}
+
+async function requireCourseMember(req, res, next) {
+  try {
+    const courseId = req.params.course_id || req.params.courseId || req.params.id;
+    const userId = req.currentUserId || _currentUserId(req);
+    const row = await _getMembership(courseId, userId);
+    if (!row) return res.status(403).json({ error: 'forbidden' });
+    req.courseRole = row.role;
+    return next();
+  } catch (e) {
+    // Fail closed on authz checks
+    return res.status(403).json({ error: 'forbidden' });
+  }
+}
+
+function denyStudentRole(req, res, next) {
+  const role = (req.courseRole || '').toLowerCase();
+  if (!role) return res.status(403).json({ error: 'forbidden' });
+  if (role === 'student') return res.status(403).json({ error: 'forbidden' });
+  return next();
+}
+
 console.log('zip ctrl keys =
 
@@
 // (existing routes...)
 
+// ------------------------------------------------------------
+// R01: enforce course isolation + role-based authorization
+// ------------------------------------------------------------
+// Student members must NOT be able to create assignments.
+router.post(
+  '/courses/:courseId/assignments',
+  requireLogin,
+  requireCourseMember,
+  denyStudentRole,
+  assignmentCtrl.createAssignment
+);
+
+// Non-members must NOT be able to list posts for an arbitrary course.
+router.get(
+  '/courses/:courseId/posts',
+  requireLogin,
+  requireCourseMember,
+  postCtrl.listPosts
+);
+
 module.exports = router;
```

[2] Evidence Mapping

* Signal: student can create assignment -> Location: src/routes/api.js:/courses/:courseId/assignments -> Fix: add login+membership+deny-student guards
* Signal: non-member can list posts -> Location: src/routes/api.js:/courses/:courseId/posts -> Fix: add login+membership guard before handler

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "Patch assumes Express route paths/handlers and a `memberships` table; if your real files/schema differ, the failing execution path won’t be hit."
