# LMS v0 - Minimal Flask MVC Application

## Setup

1.  **Create Virtual Environment**
    ```bash
    python3 -m venv venv
    source venv/bin/activate  # On Windows: venv\Scripts\activate
    ```

2.  **Install Dependencies**
    ```bash
    pip install Flask==3.0.0 Werkzeug==3.0.1
    ```

3.  **Run Application**
    ```bash
    python app.py
    ```
    The application runs on `http://127.0.0.1:5000`.

python -m pytest -q .\api
python -m pytest -q .\web



python -m pytest -q security_tests\web --ignore=security_tests\old
python -m pytest -q security_tests\api --ignore=security_tests\old



python -m pytest -q security_tests\api
## Default Admin
On first run, an admin user is created:
- **Username**: `admin`
- **Password**: `admin123`

## Usage

### HTML Interface
Navigate to `http://127.0.0.1:5000`. 
- Register/Login.
- Create a Course (you become the admin of the course).
- Add members to the course (assign roles).
- Create Posts, Assignments, Uploads, Quizzes.

### API Endpoints
All endpoints are prefixed with `/api`.

**Auth**
- `POST /api/auth/register` -> `{username, password, display_name}`
- `POST /api/auth/login` -> `{username, password}`
- `GET /api/auth/me`

**Courses**
- `GET /api/courses`
- `POST /api/courses` -> `{title, description}`
- `GET /api/courses/<id>`

**Posts**
- `POST /api/courses/<id>/posts` -> `{title, body}`

**Assignments**
- `POST /api/courses/<id>/assignments` -> `{title, description, due_at}`
- `POST /api/courses/<id>/assignments/<aid>/submissions` -> `{content_text}`

**Quizzes**
- `POST /api/courses/<id>/quizzes` -> `{title, description, open_at, due_at}`
- `POST /api/courses/<id>/quizzes/<qid>/attempts/start` -> Starts attempt, returns `attempt_id`.
- `POST /api/courses/<id>/quizzes/<qid>/attempts/<aid>/answers` -> `{question_id, answer_json}`
- `POST /api/courses/<id>/quizzes/<qid>/attempts/<aid>/submit`

## Architecture
- **MVC**: 
  - `models/` contains repository logic (SQL queries).
  - `controllers/` contains route logic (Flask Blueprints).
  - `templates/` contains Jinja2 HTML views.
- **Auth**: Session-based using Flask `session`.
- **DB**: SQLite via `sqlite3`.
