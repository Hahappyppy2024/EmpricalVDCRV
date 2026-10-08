# P01 — Learning Management System

Synthetic benchmark project aligned with the **Moodle / Moodle ecosystem** workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums (discussion board), resources (course materials), announcements, role/capability checks, and plugin-like settings.

- **Language / runtime:** PHP 8.3
- **Web framework:** Slim 4 (PSR-7 request/response handling)
- **Database:** SQLite via PDO with a repository/data-access layer
- **Authentication:** server-side sessions stored in SQLite, delivered as an HTTP-only cookie
- **Browser client:** server-served HTML, CSS, and vanilla JavaScript
- **Real-time transport:** Workerman-based local WebSocket process sharing the same SQLite database (discussion board + announcements)
- **Dependency tooling:** Composer with pinned direct dependencies and a committed `composer.lock`
- **Configuration:** environment variables documented in `.env.example`

---

## Prerequisites

- PHP **8.3+** with the `pdo_sqlite` extension (`php -m | findstr sqlite`)
- Composer 2.x
- Docker + Docker Compose (optional, for containerized execution)

Verify:

```powershell
php -v
composer --version
```

## Installation

```powershell
composer install
```

This installs the pinned dependencies from `composer.lock`:

| Package | Pinned version |
| --- | --- |
| `slim/slim` | 4.15.2 |
| `slim/psr7` | 1.8.0 |
| `workerman/workerman` | 4.1.17 |

## Environment configuration

Copy `.env.example` to `.env` (already provided) and adjust as needed:

```powershell
Copy-Item .env.example .env
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `APP_ENV` | `local` | `local` or `production` (controls error detail) |
| `BASE_URL` | `http://localhost:8080` | base URL for links and reset e-mails |
| `DB_PATH` | `data/lms.sqlite` | SQLite database file |
| `UPLOAD_DIR` | `data/uploads` | stored course-material and submission files |
| `EXPORT_DIR` | `data/exports` | generated grade-export / report files |
| `SESSION_LIFETIME_MINUTES` | `480` | session lifetime (persisted in SQLite) |
| `ALLOW_REGISTRATION` | `true` | allow public registration as a student |
| `WS_HOST` / `WS_PORT` | `127.0.0.1` / `8280` | Workerman WebSocket endpoint |
| `SITE_NAME` | `P01 Learning Management System` | site title |

## Database reset / seed

Deterministic seed fixtures cover every actor, role, relationship, workflow state, and dependency required by the acceptance criteria.

```powershell
php bin/reset.php            # apply schema + seed (idempotent)
php bin/reset.php --drop     # delete the database file, uploads, and exports, then rebuild + seed
```

### Seed accounts

| Username | Password | Role | Email |
| --- | --- | --- | --- |
| `admin` | `AdminPass123!` | admin | admin@example.com |
| `alice` | `InstructorPass123!` | instructor | alice@example.com |
| `bob` | `InstructorPass123!` | instructor | bob@example.com |
| `student1` | `StudentPass123!` | student | student1@example.com |
| `student2` | `StudentPass123!` | student | student2@example.com |
| `student3` | `StudentPass123!` | student | student3@example.com |
| `guest` | `GuestPass123!` | visitor | guest@example.com |

Seeded data includes: 6 courses (public/private, open/closed), enrollments, seeded material files, announcements, discussion threads + replies, assignments + submissions, quizzes with questions + a completed scored attempt, grade items + grades, settings, an example export, a bulk report, and an audit trail.

## Startup

Web application (PHP built-in server):

```powershell
php -S 127.0.0.1:8080 -t public
# or
composer serve
```

Real-time WebSocket process (optional but recommended; the UI degrades to HTTP refresh when it is down):

```powershell
php bin/websocket.php start
# or
composer ws
```

Open <http://localhost:8080>.

### Smoke check

Boots the app in-process and verifies every module route and role guard:

```powershell
php bin/smoke.php            # summary
php bin/smoke.php --verbose  # per-check output
```

## Docker

```bash
docker compose up --build
```

The compose service publishes:
- `8080` → web application
- `8280` → Workerman WebSocket

Data persists in the `lms_data` volume. The container entrypoint runs `bin/reset.php`, starts the WebSocket process, then serves the app.

## Usage guide

- **Student:** register or sign in, browse `/courses`, filter by category/semester/instructor, enroll, download materials, read announcements, post on the discussion board, submit assignments, take quizzes, and view your own grades at `/grades`.
- **Instructor:** create courses, upload materials, publish announcements, create assignments and quizzes, review submissions, enter grades in the gradebook, and export grades (CSV/PDF) at `/exports`.
- **Admin:** `/admin` for users/roles/courses/enrollments/settings/audit, `/admin/reports` for the bulk course report, and full grade export rights.
- **API demo:** `/api-integration` exercises loading/empty/success/error states; `/error-demo` triggers stable error responses.
- **API contract:** every module exposes `GET /api/lms/{module}`, `POST /api/lms/{module}`, and `PATCH /api/lms/{module}/{id}` returning JSON shaped `{ok, data|error}` where `error = {code, message}` (never a stack trace).

## Traceability table

| Use case | Title | Primary files / routes |
| --- | --- | --- |
| LMS-01 | Account access | `src/Controllers/AuthController.php`, `src/Services/AuthService.php`, `src/Controllers/ApiController.php` (`account_access`), `/login`, `/register`, `/forgot-password`, `/reset-password`, `/logout` |
| LMS-02 | Course discovery | `src/Repositories/CourseRepository.php` (`discover`), `ApiController` (`course_discovery`), `src/Views/courses.php`, `/courses` |
| LMS-03 | Enrollment | `CourseRepository` (`enroll`/`roster`), `ApiController` (`enrollment`), `src/Views/course_detail.php` |
| LMS-04 | Course materials | `CourseRepository` (`materialsForCourse`/`addMaterial`), `FileService`, `ApiController` (`course_materials`), `src/Views/course_detail.php`, `/download/material/{id}` |
| LMS-05 | Announcements | `CourseRepository` (`announcements*`), `RealtimeService` (WebSocket publish), `ApiController` (`announcements`), `src/Views/course_detail.php` |
| LMS-06 | Discussion board | `CourseRepository` (`threadsForCourse`/`replies*`), `RealtimeService`, `bin/websocket.php`, `ApiController` (`discussion_board`), `src/Views/discussion.php` |
| LMS-07 | Assignment submission | `src/Repositories/AssignmentRepository.php`, `FileService`, `ApiController` (`assignment_submission`), `src/Views/assignments.php`, `/download/submission/{id}` |
| LMS-08 | Quiz lifecycle | `src/Repositories/QuizRepository.php`, `ApiController` (`quiz_lifecycle`), `src/Views/quizzes.php`, `quiz_take.php`, `quiz_results.php`, `/quizzes/{id}/take` |
| LMS-09 | Grades | `src/Repositories/GradeRepository.php`, `ApiController` (`grades`), `src/Views/gradebook.php`, `grades.php`, `/courses/{id}/gradebook`, `/grades` |
| LMS-10 | Grade export | `src/Services/ExportService.php`, `SystemRepository` (`exports*`), `ApiController` (`grade_export`), `src/Views/exports.php`, `/download/export/{id}` |
| LMS-11 | Bulk course report | `SystemRepository` (`bulkStats`/`reports`), `ApiController` (`bulk_course_report`), `src/Views/admin_reports.php`, `/admin/reports` |
| LMS-12 | Administrative API | `UserRepository`, `SystemRepository` (`settings`/`auditEvents`), `ApiController` (`administrative_api`), `src/Views/admin_dashboard.php`, `/admin` |
| LMS-13 | Frontend API integration | `ApiController` (`frontend_api_integration`), `public/assets/js/app.js` (`LMS.api`/`LMS.load`), `src/Views/api_integration.php`, `/api-integration` |
| LMS-14 | Error responses | `src/Middleware/ErrorMiddleware.php`, `ApiController` (`error_responses`), `src/Views/error_demo.php`, `/error-demo`, `public/index.php` (JSON 404 catch-all) |

## Project layout

```
bin/          reset.php, smoke.php, websocket.php, entrypoint.sh
public/       index.php, .htaccess, assets/css, assets/js
src/          Config, Database, Schema, Seeder, Http, Container, App
src/Controllers/   AuthController, PageController, ApiController
src/Middleware/    AuthMiddleware, RequireRole, ErrorMiddleware
src/Repositories/  User, Course, Assignment, Quiz, Grade, System
src/Services/      AuthService, CapabilityService, Validator, FileService, ExportService, RealtimeService
src/Views/         layout + page templates
data/         SQLite database, uploads, exports (git-ignored)
```
