[1] Patch
Target File: routes/uploads.js
Target Endpoint: GET /api/courses/:course_id/uploads/:upload_id/download
Repair Action:
1. Identify the handler for the download route.
2. Extract `upload_id` from `req.params`.
3. Insert a validation guard clause before any file system operations or database lookups.
4. Validation Logic: Check if `upload_id` is a valid format (e.g., alphanumeric/UUID) or contains path traversal characters (`..`, `/`, `\`).
   - Suggested Regex for strict allowlist: `/^[a-f0-9-]{36}$/i` (if UUID) or `/^[a-zA-Z0-9-_]+$/`.
   - Suggested Regex for blocklist: `/\.\.|\//` (rejects traversal).
5. If validation fails, immediately return `res.status(400).json({ error: 'Invalid upload ID' })`.
6. Ensure the response Content-Type for the error is `application/json` to satisfy test expectations (`expect(badType).not.toMatch...`).

[2] Evidence Mapping
- Signal: Test expects status 400 for traversal payload `../../app.js` -> Location: `routes/uploads.js` handler -> Fix: Add input validation guard for `upload_id`.
- Signal: Test verifies route existence with numeric ID `999999` -> Location: Route definition -> Fix: Ensure validation runs within the handler, distinct from missing route 404s.
- Signal: Test specifies rejection of traversal payloads -> Location: Input processing -> Fix: Reject inputs containing `..` or `/` before file resolution.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The application currently processes the `upload_id` without validation, allowing path traversal and serving arbitrary files instead of rejecting the malicious input.