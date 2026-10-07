# EduCollab Flask Requirement 04

Minimal Flask + SQLite + Jinja2 MVC-style application for:
- Bootstrapping
- Auth / User v0
- Course v0
- Membership v0
- Post
- Comment
- Search
- Assignment
- Submission
- Upload

## Default admin credentials

- Username: `admin`
- Password: `admin123`

## Course role behavior

- Supported course roles: `admin`, `teacher`, `student`, `assistant`, `senior-assistant`
- When a course is created, the creator is automatically added to that course:
  - username `admin` becomes course role `admin`
  - any other creator becomes course role `teacher`
- `teacher`, `admin`, `assistant`, and `senior-assistant` can create, update, and delete assignments
- Any logged-in course member can view assignment list/detail
- Any logged-in course member can create and update their own submissions
- Course staff can view all submissions for an assignment
- Course staff can upload and delete files
- Any logged-in course member can list and download uploads

## Install

```bash
python -m venv .venv
source .venv/bin/activate  # Linux/macOS
# or on Windows PowerShell:
# .venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## Run

```bash
python app.py
```

The app starts on:
- `http://127.0.0.1:5000/`

Database file:
- `data/app.db`

Uploaded files:
- stored under `storage/uploads/`

## How to upload a file

- Sign in as a course staff member
- Open a course
- Open uploads
- Use the upload form
- The file is saved to local disk and its metadata is saved in SQLite

## API examples

Login as admin:

```bash
curl -X POST http://127.0.0.1:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -c cookies.txt -b cookies.txt \
  -d '{"username":"admin","password":"admin123"}'
```

Create course:

```bash
curl -X POST http://127.0.0.1:5000/api/courses \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title":"COMP999","description":"Requirement 04 course"}'
```

Create assignment:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/assignments \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title":"HW1","description":"Write code","due_at":"2026-03-10 23:59"}'
```

Upload file:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/uploads \
  -b cookies.txt \
  -F "file=@README.md"
```

Create submission:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/assignments/1/submissions \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"content_text":"My answer","attachment_upload_id":1}'
```

List submissions for current user:

```bash
curl http://127.0.0.1:5000/api/my/submissions -b cookies.txt
```

Download upload 1:

```bash
curl -L http://127.0.0.1:5000/api/courses/1/uploads/1/download -b cookies.txt -o downloaded_file
```
