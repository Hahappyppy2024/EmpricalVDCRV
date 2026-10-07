[1] Patch
```diff
--- /dev/null
+++ b/controllers/gradeController.js
@@ -0,0 +1,35 @@
+const db = require('../models');
+
+exports.importGrades = async (req, res) => {
+    const { assignment_id } = req.params;
+    // Assuming multer middleware is used upstream or req.file is populated
+    if (!req.file) {
+        return res.status(400).json({ error: 'No file uploaded' });
+    }
+
+    const csv = req.file.buffer.toString('utf8');
+    const lines = csv.trim().split('\n');
+    // Simple CSV parsing (skipping header)
+    const dataLines = lines.slice(1);
+    
+    let updatedCount = 0;
+
+    for (const line of dataLines) {
+        if (!line.trim()) continue;
+        const parts = line.split(',');
+        // CSV format: submission_id,score,feedback
+        const submission_id = parts[0];
+        const scoreStr = parts[1];
+
+        // Validation: Check if score is numeric
+        if (isNaN(parseFloat(scoreStr)) || !isFinite(scoreStr)) {
+            continue; // Skip non-numeric scores
+        }
+
+        await db.Submission.update({ score: scoreStr }, { where: { submission_id } });
+        updatedCount++;
+    }
+
+    res.status(200).json({ updatedCount });
+};
--- a/routes/assignments.js
+++ b/routes/assignments.js
@@ -1,3 +1,11 @@
 const express = require('express');
 const router = express.Router();
+const multer = require('multer');
+const upload = multer();
+const gradeController = require('../controllers/gradeController');
+
+// Endpoint: /api/courses/:course_id/assignments/:assignment_id/grades/import
+// Note: This router is expected to be mounted under /api/courses/:course_id/assignments/
+router.post('/:assignment_id/grades/import', upload.single('csv'), gradeController.importGrades);
+
 module.exports = router;
```

[2] Evidence Mapping
- Signal: `expect(imp.body.updatedCount).toBe(0)` fails for non-numeric input -> Location: `controllers/gradeController.js:importGrades` -> Fix: Added `isNaN` check to skip invalid rows.
- Signal: Test sends multipart/form-data -> Location: `routes/assignments.js` -> Fix: Added `multer` middleware to parse `req.file`.
- Signal: `apiCreateAssignment` creates valid submission -> Location: `controllers/gradeController.js` -> Fix: Loop targets `submission_id` for DB updates.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: "The patch creates the necessary controller and route structure to handle the file upload and validates the score, ensuring non-numeric inputs are skipped and updatedCount remains 0."