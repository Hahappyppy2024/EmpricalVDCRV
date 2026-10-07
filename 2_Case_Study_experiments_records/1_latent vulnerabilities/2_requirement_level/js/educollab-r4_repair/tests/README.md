# EduCollab (r4+) API Tests — Assignments / Submissions / Uploads

This test pack targets the **new APIs added after r1 + r2 + r3**, i.e.:

- Assignments
  - `POST /api/courses/:course_id/assignments`
  - `GET /api/courses/:course_id/assignments`
  - `GET /api/courses/:course_id/assignments/:assignment_id`
  - `PUT /api/courses/:course_id/assignments/:assignment_id`
  - `DELETE /api/courses/:course_id/assignments/:assignment_id`
- Submissions
  - `POST /api/courses/:course_id/assignments/:assignment_id/submissions`
  - `PUT /api/courses/:course_id/assignments/:assignment_id/submissions/:submission_id`
  - `GET /api/my/submissions`
  - `GET /api/courses/:course_id/assignments/:assignment_id/submissions`
- Uploads
  - `POST /api/courses/:course_id/uploads` (multipart)
  - `GET /api/courses/:course_id/uploads`
  - `GET /api/courses/:course_id/uploads/:upload_id/download`
  - `DELETE /api/courses/:course_id/uploads/:upload_id`

## Files

- `tests/api.assignments_submissions_uploads.functional.test.js`
  - Normal end-to-end workflow: assignment CRUD, submission create/update/list, upload create/list/download/delete.

- `tests/api.assignments_submissions_uploads.exploit.test.js`
  - **Exploit** verifying a likely vulnerability in the upload implementation:
    - crafted `file.originalname` containing `../` (or `..\\`) may escape `storage/uploads`.
  - This exploit test **passes when the vulnerability exists**.

## How to run

1) Copy `tests/` into the project root (same directory as `app.js`).

2) Run with Node's built-in test runner:

```bash
node --test tests/api.assignments_submissions_uploads.functional.test.js
# node --test tests/api.assignments_submissions_uploads.exploit.test.js
```

Or run both:

```bash
node --test tests/api.assignments_submissions_uploads.*.test.js
```

## Notes

- The tests spin up a fresh server by copying the repository to a temporary directory and running `node app.js` with a random port.
- The exploit test intentionally asserts that traversal succeeds. After fixing the bug, the exploit test should fail and should be replaced by a regression test asserting traversal is blocked.
