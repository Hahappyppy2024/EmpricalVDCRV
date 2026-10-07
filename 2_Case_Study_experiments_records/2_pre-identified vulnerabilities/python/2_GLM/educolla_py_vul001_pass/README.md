# EduCollab Flask MVC v0 (minimal)

Python 3.11+ + Flask + sqlite3, MVC-ish folders (repos/controllers/templates).

## Run

```bash
python -m venv .venv
# Windows:
.venv\Scripts\activate
# macOS/Linux:
# source .venv/bin/activate

pip install -r requirements.txt
python app.py
# open http://127.0.0.1:5000
```

On first request, it auto-creates `./data/app.db` and seeds:
- username: `admin`
- password: `admin123`

## Key API checks (curl)

```bash
# login as admin
curl -i -c admin.txt -X POST http://127.0.0.1:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# create course (creator becomes teacher)
curl -s -b admin.txt -X POST http://127.0.0.1:5000/api/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"C1","description":"demo"}'

# list courses
curl -s -b admin.txt http://127.0.0.1:5000/api/courses
```

## Notes
- Cookie-based Flask session is used.
- Time fields stored as ISO strings.
- Intentionally minimal.
