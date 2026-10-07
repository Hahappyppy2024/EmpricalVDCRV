# EduCollab Flask Requirement 03

Minimal Flask + SQLite + Jinja2 MVC-style application for:
- Bootstrapping
- Auth / User v0
- Course v0
- Membership v0
- Post
- Comment
- Search

## Default admin credentials

- Username: `admin`
- Password: `admin123`

## Course role behavior

- Supported course roles: `admin`, `teacher`, `student`, `assistant`, `senior-assistant`
- When a course is created, the creator is automatically added to that course:
  - username `admin` becomes course role `admin`
  - any other creator becomes course role `teacher`
- `teacher` and `admin` can add members, update roles, and remove members
- Any logged-in user can view a course member list
- Any logged-in user can view their own memberships
- Any logged-in course member can create, read, update, delete, and search posts/comments in that course

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
  -d '{"title":"COMP999","description":"Requirement 03 course"}'
```

Add member to course 1:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/members \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"user_id":2,"role_in_course":"student"}'
```

Create post in course 1:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/posts \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title":"Welcome","body":"First post body"}'
```

List posts in course 1:

```bash
curl http://127.0.0.1:5000/api/courses/1/posts -b cookies.txt
```

Create comment on post 1 in course 1:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/posts/1/comments \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"body":"Nice post"}'
```

Search posts in course 1:

```bash
curl "http://127.0.0.1:5000/api/courses/1/search/posts?keyword=Welcome" -b cookies.txt
```

Search comments in course 1:

```bash
curl "http://127.0.0.1:5000/api/courses/1/search/comments?keyword=Nice" -b cookies.txt
```
