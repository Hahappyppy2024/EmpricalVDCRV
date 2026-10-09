# P01 — Learning Management System (PHP / Slim 4)

A self-contained, offline-runnable synthetic LMS benchmark aligned with the
Moodle workflow anchors (courses, enrollments, assignments, quizzes,
gradebook, forums, resources, role/capability checks, plugin-like
settings). The PHP edition is built on **PHP 8.3 + Slim 4 + SQLite + PDO
+ vanilla HTML/CSS/JS**, with deterministic local adapters and a
Workerman-style WebSocket bridge.

## Stack

- PHP 8.3 (Composer 2.x)
- Slim 4 with PSR-7 (slim/psr7 + nyholm/psr7)
- PHP-DI 7 for service wiring
- SQLite via PDO (file at `storage/lms.sqlite`)
- Server-side sessions with HTTP-only cookies, persisted in SQLite
- Plain HTML / CSS / vanilla JavaScript browser pages
- Workerman-style WS bridge sharing the same SQLite data (optional)

## Prerequisites

- PHP 8.3 with extensions: `pdo`, `pdo_sqlite`, `sqlite3`, `mbstring`, `json`
- Composer 2.x
- Optional: Docker / docker compose for container runs

## Setup (local)

```powershell
# 1) Install PHP dependencies
composer install

# 2) Copy environment template
copy .env.example .env

# 3) Initialize and seed the SQLite database
php bin/init-db.php

# 4) Start the built-in PHP server (recommended)
php -S 0.0.0.0:8080 -t public public/index.php
```

Open <http://localhost:8080>.

## Setup (Docker)

```powershell
docker compose build
docker compose up -d app
docker compose exec app php bin/init-db.php
docker compose logs -f app
```

## Seeded accounts

| Role        | Username    | Password           |
| ----------- | ----------- | ------------------ |
| Admin       | admin1      | `Admin!Pass1`      |
| Instructor  | inst_anna   | `Instructor!Pass1` |
| Instructor  | inst_brian  | `Instructor!Pass1` |
| Instructor  | inst_carla  | `Instructor!Pass1` |
| Student     | stu_olivia  | `Student!Pass1`    |
| Student     | stu_peter   | `Student!Pass1`    |
| Student     | stu_quinn   | `Student!Pass1`    |
| Student     | stu_ruby    | `Student!Pass1`    |
| Student     | stu_sam     | `Student!Pass1`    |
| Student     | stu_tara    | `Student!Pass1`    |

Additional deterministic seed: 5 courses (CS101, MATH201, ENG110, PHYS150,
CS220) with enrollments, materials, announcements, discussions,
assignments, submissions, one quiz with three questions, and grades.

## Database commands

```powershell
# Recreate from scratch with deterministic seed
php bin/init-db.php

# Inspect
sqlite3 storage/lms.sqlite ".tables"
sqlite3 storage/lms.sqlite "SELECT id, code, title, visibility FROM courses;"
```

## Startup commands

```powershell
# Built-in PHP web server (recommended for local development)
php -S 0.0.0.0:8080 -t public public/index.php

# Optional WebSocket bridge (separate terminal)
php bin/workerman_ws.php

# Smoke check (in-process verification of routes / auth flow)
php bin/smoke.php

# Docker
docker compose up
```

## Docker commands

```powershell
docker compose build        # build images
docker compose up -d app    # start app service
docker compose down         # stop services
docker compose exec app php bin/init-db.php  # reseed
```

## Environment

All values live in `.env`. The most important ones are documented in
`.env.example`. The application refuses to start if `vendor/` is missing.

## Project layout

```
P01/
├── bin/                # CLI: init-db, workerman WS
├── config/             # Settings (loaded from .env)
├── database/           # SQLite schema
├── public/             # Web entrypoint + static assets
├── src/                # Application source (PSR-4 LMS\)
├── storage/            # SQLite DB, uploads, exports, logs
├── templates/          # Plain PHP templates
├── Dockerfile
├── docker-compose.yml
├── composer.json
└── README.md
```

## Use-case traceability

| Use case | Title                       | Primary routes / files                                                                                                |
| -------- | --------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| LMS-01   | Account access              | `src/Controller/AccountAccessController.php`, `src/Auth/AuthService.php`, `/login`, `/register`, `/forgot`, `/reset/{token}`, `/dashboard`, `/logout`, `/api/lms/account_access[/{id}]` |
| LMS-02   | Course discovery            | `src/Controller/CourseDiscoveryController.php`, `src/Repository/CourseRepository.php`, `/courses`, `/courses/{id}`, `/api/lms/course_discovery[/{id}]` |
| LMS-03   | Enrollment                  | `src/Controller/EnrollmentController.php`, `src/Repository/EnrollmentRepository.php`, `/enroll/{id}`, `/enroll/{id}/drop`, `/my/courses`, `/courses/{id}/roster`, `/api/lms/enrollment[/{id}]` |
| LMS-04   | Course materials            | `src/Controller/CourseMaterialController.php`, `src/Repository/MaterialRepository.php`, `/courses/{id}/materials`, `/courses/{id}/materials/new`, `/materials/{id}/download`, `/api/lms/course_materials[/{id}]` |
| LMS-05   | Announcements               | `src/Controller/AnnouncementController.php`, `src/Repository/AnnouncementRepository.php`, `/courses/{id}/announcements`, `/courses/{id}/announcements/new`, `/api/lms/announcements[/{id}]` |
| LMS-06   | Discussion board            | `src/Controller/DiscussionBoardController.php`, `src/Repository/DiscussionRepository.php`, `/courses/{id}/discussion[/new]`, `/discussion/{id}/reply|edit|delete`, `/api/lms/discussion_board[/{id}]` |
| LMS-07   | Assignment submission       | `src/Controller/AssignmentSubmissionController.php`, `src/Repository/AssignmentRepository.php`, `/courses/{id}/assignments`, `/courses/{id}/assignments/new`, `/assignments/{id}/submit`, `/assignments/{id}/submissions`, `/submissions/{id}/grade`, `/api/lms/assignment_submission[/{id}]` |
| LMS-08   | Quiz lifecycle              | `src/Controller/QuizLifecycleController.php`, `src/Repository/QuizRepository.php`, `/courses/{id}/quizzes`, `/courses/{id}/quizzes/new`, `/quizzes/{id}/take`, `/quizzes/{id}/attempts`, `/api/lms/quiz_lifecycle[/{id}]` |
| LMS-09   | Grades                      | `src/Controller/GradesController.php`, `src/Repository/GradeRepository.php`, `/grades`, `/courses/{id}/grades/new`, `/grades/{id}/update` |
| LMS-10   | Grade export                | `src/Controller/GradeExportController.php`, `src/Service/ExportService.php`, `/courses/{id}/export`, `/exports/{id}/download`, `/exports`, `/api/lms/grade_export[/{id}]` |
| LMS-11   | Bulk course report          | `src/Controller/BulkCourseReportController.php`, `/admin/reports/bulk`, `/reports/{id}/download`, `/api/lms/bulk_course_report[/{id}]` |
| LMS-12   | Administrative API          | `src/Controller/AdminApiController.php`, `/admin`, `/admin/users`, `/admin/courses`, `/admin/courses/new`, `/admin/settings`, `/admin/audit`, `/api/lms/administrative_api[/{id}]` |
| LMS-13   | Frontend API integration    | `src/Controller/FrontendApiController.php`, `/frontend-api`, `public/assets/app.js`, `templates/frontend_api.php` |
| LMS-14   | Error responses             | `src/Controller/ErrorController.php`, `/error-demo/{kind}`, `/api/lms/error_responses[/{id}]`, error middleware in `src/AppBuilder.php` |

## Common URLs to try

- `GET /` — landing page
- `GET /login` — sign in (use a seeded account above)
- `GET /register` — create a new student/instructor account
- `GET /courses` — discovery with filters
- `GET /frontend-api` — exercise loading / validation / empty / error states
- `GET /admin` — admin landing (sign in as `admin1`)
- `GET /error-demo/notfound` — deterministic error page
- `GET /api/lms/error_responses?trigger=notfound` — deterministic JSON error
- `GET /healthz` — `{"status":"ok"}`

## Notes on deterministic offline behaviour

- All persistent data lives in `storage/lms.sqlite`.
- All "external" services are deterministic local adapters: file uploads
  go to `storage/uploads`, exports go to `storage/exports`, no email or
  payment gateways are called.
- The Workerman WS bridge (`bin/workerman_ws.php`) is an optional stream-
  socket based WebSocket broadcaster that shares the same SQLite data and
  emits periodic heartbeat events. It is not required for HTTP traffic.
