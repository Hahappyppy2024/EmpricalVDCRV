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
