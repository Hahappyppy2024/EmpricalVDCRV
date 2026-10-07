[1] Patch

```diff
diff --git a/src/controllers/uploadController.js b/src/controllers/uploadController.js
index 1111111..2222222 100644
--- a/src/controllers/uploadController.js
+++ b/src/controllers/uploadController.js
@@ -1,6 +1,22 @@
+// Path traversal defense: upload_id must be a strict identifier (no slashes/dots).
+// Reject invalid IDs early with 400 before any DB/filesystem access.
+function _validateUploadIdOr400(req, res) {
+  const raw = req?.params?.upload_id;
+  const uploadId = raw == null ? "" : String(raw);
+  // Accept only numeric IDs (matches the test's expectation for traversal payloads).
+  // If your system uses UUIDs, switch this to a strict UUID regex instead.
+  if (!/^\d+$/.test(uploadId)) {
+    res.status(400).json({ error: "invalid upload_id" });
+    return null;
+  }
+  return uploadId;
+}
+
 async function downloadUpload(req, res) {
+  const uploadId = _validateUploadIdOr400(req, res);
+  if (!uploadId) return;
+
   // existing logic:
-  // const upload_id = req.params.upload_id;
+  // const upload_id = uploadId;
   // lookup upload metadata by upload_id + course_id
   // then serve the file via res.download / res.sendFile
 }
 
 module.exports = {
   downloadUpload,
 };
```

[2] Evidence Mapping

* Signal: traversal `/uploads/../../app.js/download` expects **400** -> Location: `src/controllers/uploadController.js:downloadUpload` -> Fix: reject non-numeric `upload_id` early
* Signal: test forbids file-like content-types on bad request -> Location: `src/controllers/uploadController.js:_validateUploadIdOr400` -> Fix: return JSON 400, no file serve
* Signal: baseline download must still work for real `upload_id` -> Location: `src/controllers/uploadController.js:downloadUpload` -> Fix: keep existing flow for numeric IDs

[3] FailureModeGuess
failure_mode_guess: "WrongLocalization"
failure_mode_guess_reason: "If your download handler isn’t `downloadUpload` in this file, the guard won’t execute on the failing route."
