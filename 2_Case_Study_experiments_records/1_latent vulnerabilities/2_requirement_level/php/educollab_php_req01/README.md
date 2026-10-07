# EduCollab PHP Requirement 01

Minimal PHP 8+ MVC-style application for bootstrapping, auth/user, and course management.

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

The application also auto-runs DB initialization and admin seeding on server start.

## Default admin credentials

- username: `admin`
- password: `admin123`

## Main routes

- `GET /register`, `POST /register`
- `GET /login`, `POST /login`
- `POST /logout`
- `GET /me`
- `GET /admin/users`
- `GET /courses`
- `GET /courses/new`, `POST /courses`
- `GET /courses/:course_id`
- `GET /courses/:course_id/edit`, `POST /courses/:course_id`
- `POST /courses/:course_id/delete`
- `/api/...` equivalents for auth, users, and courses

## Example curl commands

Register:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"alice","password":"pass123","display_name":"Alice"}'
```

Login:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Current user:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/auth/me
```

List users:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/users
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"Intro to Secure Coding","description":"Requirement 01 demo course"}'
```

List courses:

```bash
curl -i http://127.0.0.1:8000/api/courses
```

Get course:

```bash
curl -i http://127.0.0.1:8000/api/courses/1
```

Update course:

```bash
curl -i -b cookie.txt -X PUT http://127.0.0.1:8000/api/courses/1 \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated Title","description":"Updated description"}'
```

Delete course:

```bash
curl -i -b cookie.txt -X DELETE http://127.0.0.1:8000/api/courses/1
```
