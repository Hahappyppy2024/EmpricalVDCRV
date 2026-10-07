# EduCollab PHP Requirement 04

Minimal PHP 8+ MVC-style application for bootstrapping, auth/user, course management, membership, posts, comments, search, assignments, submissions, and uploads.

## Requirements

- PHP 8+
- PDO SQLite extension enabled

## Install

No external dependencies are required.

## Run

```bash
php scripts/init.php
php -S 127.0.0.1:8000 -t public
```

The application auto-runs DB initialization, admin seeding, course creator membership backfill, and upload storage setup on server start.

## Default admin credentials

- username: `admin`
- password: `admin123`

## Upload behavior

- Upload storage directory: `storage/uploads/`
- Metadata is stored in the `uploads` table
- Use HTML form upload at `/courses/:course_id/uploads/new`
- API upload uses `multipart/form-data` with field name `file`

## Key role rules

- `teacher`, `assistant`, `senior-assistant`, and course `admin` can create/update/delete assignments
- any logged-in course member can view assignments
- any logged-in course member can create/update their own submissions
- course staff can view all submissions for an assignment
- course staff can upload/delete files
- any logged-in course member can list and download uploads

## Example curl commands

Login:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"Course A","description":"Demo"}'
```

Create assignment:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/assignments \
  -H "Content-Type: application/json" \
  -d '{"title":"HW1","description":"Solve tasks","due_at":"2026-03-10T23:59:00"}'
```

List assignments:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/assignments
```

Upload file:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/uploads \
  -F "file=@./example.txt"
```

List uploads:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/uploads
```

Download upload:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/uploads/1/download -o downloaded.bin
```

Create submission:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/assignments/1/submissions \
  -H "Content-Type: application/json" \
  -d '{"content_text":"My answer","attachment_upload_id":1}'
```

Update submission:

```bash
curl -i -b cookie.txt -X PUT http://127.0.0.1:8000/api/courses/1/assignments/1/submissions/1 \
  -H "Content-Type: application/json" \
  -d '{"content_text":"Updated answer","attachment_upload_id":1}'
```

My submissions:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/my/submissions
```

List submissions for assignment:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/assignments/1/submissions
```
