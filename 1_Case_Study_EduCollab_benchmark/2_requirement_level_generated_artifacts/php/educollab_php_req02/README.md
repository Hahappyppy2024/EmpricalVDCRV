# EduCollab PHP Requirement 02

Minimal PHP 8+ MVC-style application for bootstrapping, auth/user, course management, membership, and course-scoped role enforcement.

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

The application also auto-runs DB initialization, admin seeding, and course creator membership backfill on server start.

## Default admin credentials

- username: `admin`
- password: `admin123`

## Membership and course roles

- Membership roles: `admin`, `teacher`, `student`, `assistant`, `senior-assistant`
- When a user creates a course, that user is automatically added to the course as `teacher`
- Existing courses are backfilled so each course creator also has a `teacher` membership
- `teacher` and `admin` in a course can add members, update roles, and remove members
- Any logged-in course member can view that course's member list
- Any logged-in user can view only their own memberships through `/memberships` or `/api/memberships`
- Course-scoped guard helpers are provided for later modules:
  - `require_login`
  - `require_course_member`
  - `require_teacher_or_admin`
  - `require_course_staff`

## Main routes

- Requirement 01 routes remain available
- `GET /memberships`
- `GET /courses/:course_id/members`
- `GET /courses/:course_id/members/new`
- `POST /courses/:course_id/members`
- `POST /courses/:course_id/members/:membership_id`
- `POST /courses/:course_id/members/:membership_id/delete`
- `/api/memberships`
- `GET /api/courses/:course_id/members`
- `POST /api/courses/:course_id/members`
- `PUT /api/courses/:course_id/members/:membership_id`
- `DELETE /api/courses/:course_id/members/:membership_id`

## Example curl commands

Register:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"alice","password":"pass123","display_name":"Alice"}'
```

Login as admin:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"Intro to Secure Coding","description":"Requirement 02 demo course"}'
```

List members in course 1:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/members
```

Add user 2 as student in course 1:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/members \
  -H "Content-Type: application/json" \
  -d '{"user_id":2,"role_in_course":"student"}'
```

Update membership 2 to assistant:

```bash
curl -i -b cookie.txt -X PUT http://127.0.0.1:8000/api/courses/1/members/2 \
  -H "Content-Type: application/json" \
  -d '{"role_in_course":"assistant"}'
```

Remove membership 2:

```bash
curl -i -b cookie.txt -X DELETE http://127.0.0.1:8000/api/courses/1/members/2
```

My memberships:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/memberships
```
