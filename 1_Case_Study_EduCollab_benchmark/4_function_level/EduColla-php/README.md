# EduCollab PHP v0 (Plain PHP + SQLite, MVC-style)

A minimal, working MVC-style PHP 8+ web app with **HTML pages** and **JSON REST API** under `/api`, sharing the same SQLite DB and session auth.

## Requirements
- PHP 8+
- SQLite enabled in PHP (PDO SQLite)

## Run
From the project root:

```bash
php -S localhost:8000 -t public
```
```bash
php -S localhost:8000 -t public
```
py -3.12 -m pytest -q tests/
py -3.12 -m pytest -q tests/test_FL_FR1_unzip_submission_attachment_functional.py

python -m pytest -q




Open:
- http://localhost:8000/login

Seeded default admin:
- username: `admin`
- password: `admin123`

DB file:
- `./data/app.db` (auto-created)

Uploads:
- stored under `./storage/uploads/<course_id>/...`

## Quick UI flow
1) Login as `admin`.
2) Create a course.
3) Add members in **Course → Membership** (use user_id from Admin → Users).
4) Use modules: Posts/Comments/Search, Assignments/Submissions, Uploads, Question Bank, Quizzes.

## API usage (curl)
This app uses cookie-based sessions. Use a cookie jar file to persist login.

### 1) Register (student)
```bash
curl -i -c cookies.txt -H "Content-Type: application/json" \
  -d '{"username":"s1","password":"pass","display_name":"Student 1"}' \
  http://localhost:8000/api/auth/register
```

### 2) Login
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  http://localhost:8000/api/auth/login
```

### 3) Create course
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"title":"CS Demo","description":"demo course"}' \
  http://localhost:8000/api/courses
```

### 4) Add member (teacher/admin only)
```bash
# replace course_id and user_id
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"user_id":2,"role_in_course":"student"}' \
  http://localhost:8000/api/courses/1/members
```

### 5) Create a post
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"title":"Hello","body":"First post"}' \
  http://localhost:8000/api/courses/1/posts
```

### 6) Search posts
```bash
curl -s -b cookies.txt "http://localhost:8000/api/courses/1/search/posts?keyword=Hello"
```

### 7) Create assignment (staff)
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"title":"HW1","description":"do stuff","due_at":"2026-03-01T00:00:00Z"}' \
  http://localhost:8000/api/courses/1/assignments
```

### 8) Upload file (staff)
```bash
curl -i -c cookies.txt -b cookies.txt \
  -F "file=@README.md" \
  http://localhost:8000/api/courses/1/uploads
```

### 9) Create question (staff)
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"qtype":"text","prompt":"2+2?","answer_json":"{\"value\":4}"}' \
  http://localhost:8000/api/courses/1/questions
```

### 10) Create quiz + configure questions (staff)
```bash
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"title":"Quiz 1","description":"demo","open_at":"","due_at":""}' \
  http://localhost:8000/api/courses/1/quizzes

curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"question_id":1,"points":5,"position":1}' \
  http://localhost:8000/api/courses/1/quizzes/1/questions
```

### 11) Student take quiz (student role)
```bash
# login as student first, then:
curl -i -c cookies.txt -b cookies.txt \
  http://localhost:8000/api/courses/1/quizzes/1/attempts/start

# answer
curl -i -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"question_id":1,"answer_json":"{\"value\":4}"}' \
  http://localhost:8000/api/courses/1/quizzes/1/attempts/1/answers

# submit
curl -i -c cookies.txt -b cookies.txt \
  http://localhost:8000/api/courses/1/quizzes/1/attempts/1/submit
```

### PUT/DELETE notes
This app supports real `PUT`/`DELETE` for API routes (e.g., `curl -X PUT ...`).

Example:
```bash
curl -i -X PUT -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"title":"Updated","description":"..."}' \
  http://localhost:8000/api/courses/1
```

## Folder structure (MVC)
- `public/` front controller (router)
- `src/repos/` repositories (DB access)
- `src/controllers/` controllers (route handlers)
- `views/` server-rendered templates
- `data/` SQLite DB
- `storage/` uploads


## Added modules: Materials (ZIP) + CSV import/export

- Materials UI:
  - GET  /courses/{course_id}/materials
  - GET  /courses/{course_id}/materials/upload-zip
  - POST /courses/{course_id}/materials/upload-zip (multipart)
  - GET  /courses/{course_id}/materials/download-zip

- Members CSV:
  - GET  /courses/{course_id}/members/export.csv
  - POST /courses/{course_id}/members/import.csv (multipart)

- Grades CSV:
  - GET  /courses/{course_id}/assignments/{assignment_id}/grades.csv

API:
  - GET  /api/courses/{course_id}/materials/files
  - POST /api/courses/{course_id}/materials/upload-zip
  - GET  /api/courses/{course_id}/materials/download-zip
  - GET  /api/courses/{course_id}/members/export.csv
  - POST /api/courses/{course_id}/members/import.csv
  - GET  /api/courses/{course_id}/assignments/{assignment_id}/grades.csv

Note: ZIP upload/extract requires the PHP ZipArchive extension.


## v1 Function-level extensions (Assignments ZIP + Grades CSV)

API endpoints:
- POST /api/courses/{cid}/assignments/{aid}/submissions/{sid}/unzip
- GET  /api/courses/{cid}/assignments/{aid}/submissions/{sid}/files
- POST /api/courses/{cid}/assignments/{aid}/submissions/export-zip
- GET  /api/courses/{cid}/assignments/{aid}/submissions/export-zip/{job_id}/download
- GET  /api/courses/{cid}/assignments/{aid}/grades.csv
- POST /api/courses/{cid}/assignments/{aid}/grades/import.csv (multipart file)



====================================================================== short test summary info =======================================================================
FAILED tests/test_FL_FR5_export_grades_csv_functional.py::test_FL_FR5_export_grades_csv_functional - AssertionError: Not Found
FAILED tests/test_FL_FR6_import_grades_csv_functional.py::test_FL_FR6_import_grades_csv_functional - AssertionError: import_grades failed status=404 body=Not Found
FAILED tests/test_RL_CSV1_export_members_functional.py::test_RL_CSV1_export_members_functional - AssertionError: Not Found
FAILED tests/test_RL_CSV2_import_members_functional.py::test_RL_CSV2_import_members_functional - AssertionError: status=404 ct=text/html; charset=UTF-8 body=Not Found
FAILED tests/test_RL_CSV3_export_assignment_grades_functional.py::test_RL_CSV3_export_assignment_grades_functional - AssertionError: Not Found
FAILED tests/test_RL_MAT1_upload_zip_extract_functional.py::test_RL_MAT1_upload_zip_extract_functional - AssertionError: {"error":"upload error"}
FAILED tests/test_RL_MAT2_list_materials_files_functional.py::test_RL_MAT2_list_materials_files_functional - AssertionError: {"error":"upload error"}
FAILED tests/test_RL_MAT3_download_materials_zip_functional.py::test_RL_MAT3_download_materials_zip_functional - AssertionError: {"error":"upload error"}