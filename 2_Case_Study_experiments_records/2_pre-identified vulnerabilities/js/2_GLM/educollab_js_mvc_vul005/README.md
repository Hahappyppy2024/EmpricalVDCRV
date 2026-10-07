# EduCollab JS MVC v0 (Node.js + Express + SQLite)

Minimal but working MVC-style web app.

## Tech
- Node.js (LTS)
- Express
- SQLite via **better-sqlite3**
- Sessions via express-session
- Views via EJS
- HTML pages + JSON REST API under `/api` share the same DB + session auth

## Default admin
- username: **admin**
- password: **admin123**

## Run
```bash
npm install
npm start
# open http://localhost:3000
```

DB file is created at `./data/app.db` automatically on server start.

## Quick UI flow
1. Login as `admin/admin123`
2. Create a course
3. Add members to the course (teacher/admin only)
4. Posts/comments/search (any logged-in course member)
5. Staff: create assignments, upload files, manage question bank + quizzes
6. Student: submit assignment and take quizzes

## API examples (curl)
> Tip: use `-c cookies.txt -b cookies.txt` to keep the same session.

### Register student + login (session)
```bash
curl -s -c cookies.txt -X POST http://localhost:3000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"username":"s1","password":"pass","display_name":"Student 1"}'

curl -s -c cookies.txt -b cookies.txt http://localhost:3000/api/auth/me
```

### Login as admin
```bash
curl -s -c cookies.txt -X POST http://localhost:3000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin123"}'
```

### Create course (admin)
```bash
curl -s -c cookies.txt -b cookies.txt -X POST http://localhost:3000/api/courses \
  -H 'Content-Type: application/json' \
  -d '{"title":"CS101","description":"Intro"}'
```

### List courses
```bash
curl -s -b cookies.txt http://localhost:3000/api/courses
```

### Add member (teacher/admin)
```bash
# course_id=1, user_id=2
curl -s -b cookies.txt -X POST http://localhost:3000/api/courses/1/members \
  -H 'Content-Type: application/json' \
  -d '{"user_id":2,"role_in_course":"student"}'
```

### Posts + comments
```bash
curl -s -b cookies.txt -X POST http://localhost:3000/api/courses/1/posts \
  -H 'Content-Type: application/json' \
  -d '{"title":"Hello","body":"First post"}'

curl -s -b cookies.txt http://localhost:3000/api/courses/1/posts

curl -s -b cookies.txt -X POST http://localhost:3000/api/courses/1/posts/1/comments \
  -H 'Content-Type: application/json' \
  -d '{"body":"Nice"}'
```

### Upload (staff) (multipart)
```bash
curl -s -b cookies.txt -X POST http://localhost:3000/api/courses/1/uploads \
  -F file=@README.md
```

### Search
```bash
curl -s -b cookies.txt "http://localhost:3000/api/courses/1/search/posts?keyword=Hello"
```

## Folder structure (MVC)
- `src/repos/*` : DB access only (better-sqlite3)
- `src/controllers/*` : route handlers calling repos
- `src/views/*` : EJS templates
- `src/routes/web.js` : HTML routes
- `src/routes/api.js` : JSON API routes

