# EduCollab PHP Requirement 05

Minimal PHP 8+ MVC-style application for bootstrapping, auth/user, course management, membership, posts, comments, search, assignments, submissions, uploads, question bank, quizzes, and student quiz attempts.

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

The application auto-runs DB initialization, admin seeding, course creator membership backfill, and upload storage directory creation on server start.

## Default admin credentials

- username: `admin`
- password: `admin123`

## Roles used in Requirement 05

- Staff roles for question bank and quiz management: `teacher`, `assistant`, `senior-assistant`, `admin`
- Student-only quiz attempt actions require `role_in_course = student`
- Any logged-in course member can view quiz list and quiz detail

## Uploads

- Upload HTML/API forms use standard PHP upload handling
- Uploaded files are stored in `storage/uploads/`
- Upload metadata is stored in SQLite `uploads.storage_path`

## Main new routes in Requirement 05

- `GET /courses/:course_id/questions`
- `GET /courses/:course_id/questions/new`
- `POST /courses/:course_id/questions`
- `GET /courses/:course_id/questions/:question_id`
- `GET /courses/:course_id/questions/:question_id/edit`
- `POST /courses/:course_id/questions/:question_id`
- `POST /courses/:course_id/questions/:question_id/delete`
- `GET /courses/:course_id/quizzes`
- `GET /courses/:course_id/quizzes/new`
- `POST /courses/:course_id/quizzes`
- `GET /courses/:course_id/quizzes/:quiz_id`
- `GET /courses/:course_id/quizzes/:quiz_id/edit`
- `POST /courses/:course_id/quizzes/:quiz_id`
- `POST /courses/:course_id/quizzes/:quiz_id/delete`
- `GET /courses/:course_id/quizzes/:quiz_id/questions`
- `POST /courses/:course_id/quizzes/:quiz_id/questions`
- `POST /courses/:course_id/quizzes/:quiz_id/start`
- `POST /courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/answers`
- `POST /courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/submit`
- `GET /my/quizzes`
- API equivalents under `/api`

## Example curl commands

Login:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Create question:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/questions \
  -H "Content-Type: application/json" \
  -d '{"qtype":"mcq","prompt":"2+2=?","options_json":"[""3"",""4"",""5""]","answer_json":"{""correct"":""4""}"}'
```

List questions:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/questions
```

Create quiz:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/quizzes \
  -H "Content-Type: application/json" \
  -d '{"title":"Quiz 1","description":"Week 1 quiz","open_at":"2026-03-06T09:00:00","due_at":"2026-03-07T23:59:00"}'
```

Configure quiz question:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/quizzes/1/questions \
  -H "Content-Type: application/json" \
  -d '{"question_id":1,"points":5,"position":1}'
```

Delete quiz-question mapping:

```bash
curl -i -b cookie.txt -X DELETE http://127.0.0.1:8000/api/courses/1/quizzes/1/questions/1
```

Start attempt (student session):

```bash
curl -i -b student_cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/quizzes/1/attempts/start
```

Answer question (student session):

```bash
curl -i -b student_cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/quizzes/1/attempts/1/answers \
  -H "Content-Type: application/json" \
  -d '{"question_id":1,"answer_json":"{""answer"":""4""}"}'
```

Submit attempt (student session):

```bash
curl -i -b student_cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/quizzes/1/attempts/1/submit
```

View my attempts:

```bash
curl -i -b student_cookie.txt http://127.0.0.1:8000/api/my/quizzes/attempts
```
