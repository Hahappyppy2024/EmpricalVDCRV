# EduCollab Flask Requirement 02

Minimal Flask + SQLite + Jinja2 MVC-style application for:
- Bootstrapping
- Auth / User v0
- Course v0
- Membership v0
- Course-scoped role enforcement helpers

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
- Shared guards included for later modules:
  - `login_required`
  - `require_course_member`
  - `require_teacher_or_admin`
  - `require_course_staff`

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

Register:

```bash
curl -X POST http://127.0.0.1:5000/api/auth/register   -H "Content-Type: application/json"   -c cookies.txt   -d '{"username":"student1","password":"pass123","display_name":"Student One"}'
```

Login as admin:

```bash
curl -X POST http://127.0.0.1:5000/api/auth/login   -H "Content-Type: application/json"   -c cookies.txt -b cookies.txt   -d '{"username":"admin","password":"admin123"}'
```

Create course:

```bash
curl -X POST http://127.0.0.1:5000/api/courses   -H "Content-Type: application/json"   -b cookies.txt   -d '{"title":"COMP999","description":"Requirement 02 course"}'
```

List members in course 1:

```bash
curl http://127.0.0.1:5000/api/courses/1/members -b cookies.txt
```

Add member to course 1:

```bash
curl -X POST http://127.0.0.1:5000/api/courses/1/members   -H "Content-Type: application/json"   -b cookies.txt   -d '{"user_id":2,"role_in_course":"student"}'
```

Update member role in course 1:

```bash
curl -X PUT http://127.0.0.1:5000/api/courses/1/members/2   -H "Content-Type: application/json"   -b cookies.txt   -d '{"role_in_course":"assistant"}'
```

Remove member from course 1:

```bash
curl -X DELETE http://127.0.0.1:5000/api/courses/1/members/2 -b cookies.txt
```

List current user memberships:

```bash
curl http://127.0.0.1:5000/api/memberships -b cookies.txt
```
