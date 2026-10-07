4.0 VulType
VulType: R01

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/uploads/:upload_id/download
- MISSING: Controller logic for serving uploaded files

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that accepts upload_id and sends a file in response

4.3 RootCause
- The application constructs the file path for downloads using the user-supplied upload_id without sanitization or validation.
- It fails to check for path traversal sequences (e.g., ../) or ensure the resolved path remains within the designated upload directory, allowing attackers to access arbitrary files on the server.

4.4 ActionablePlan
- Target File: routes/uploads.js (or controller handling the download)
  Target: Download endpoint handler
  Change: Add strict input validation for upload_id (e.g., allow only alphanumeric/UUID characters) or use a secure file serving library that canonicalizes paths and verifies the target is within the allowed directory. Return 400 Bad Request if validation fails.

4.5 FileToActionMap
- routes/uploads.js → Add path traversal validation to the download handler.