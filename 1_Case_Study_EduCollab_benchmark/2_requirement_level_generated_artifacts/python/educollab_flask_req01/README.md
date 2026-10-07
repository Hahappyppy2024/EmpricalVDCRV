# EduCollab Flask Requirement 01

Minimal Flask + SQLite + Jinja2 MVC-style application for:
- Bootstrapping
- Auth / User v0
- Course v0

## Default admin credentials

- Username: `admin`
- Password: `admin123`

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
curl -X POST http://127.0.0.1:5000/api/auth/register \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"username":"student1","password":"pass123","display_name":"Student One"}'
```

Login:

```bash
curl -X POST http://127.0.0.1:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -c cookies.txt -b cookies.txt \
  -d '{"username":"admin","password":"admin123"}'
```

Get current user:

```bash
curl http://127.0.0.1:5000/api/auth/me -b cookies.txt
```

List users:

```bash
curl http://127.0.0.1:5000/api/users -b cookies.txt
```

Create course:

```bash
curl -X POST http://127.0.0.1:5000/api/courses \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title":"COMP999","description":"Requirement 01 course"}'
```

List courses:

```bash
curl http://127.0.0.1:5000/api/courses
```

Get one course:

```bash
curl http://127.0.0.1:5000/api/courses/1
```

Update course:

```bash
curl -X PUT http://127.0.0.1:5000/api/courses/1 \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title":"COMP999 Updated","description":"Updated description"}'
```

Delete course:

```bash
curl -X DELETE http://127.0.0.1:5000/api/courses/1 -b cookies.txt
```
