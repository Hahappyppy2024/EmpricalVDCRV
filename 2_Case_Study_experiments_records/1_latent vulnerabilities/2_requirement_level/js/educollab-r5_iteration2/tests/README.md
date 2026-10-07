# EduCollab (R5) — New API Tests (Questions / Quizzes / Attempts)

This test bundle targets the APIs added after r1+r2+r3+r4 (i.e., **Question Bank + Quiz + Student Attempts**).

## Covered APIs

### Question bank
- `POST /api/courses/:course_id/questions` (course staff)
- `GET /api/courses/:course_id/questions` (course member)
- `GET /api/courses/:course_id/questions/:question_id` (course member)
- `PUT /api/courses/:course_id/questions/:question_id` (course staff)
- `DELETE /api/courses/:course_id/questions/:question_id` (course staff)

### Quizzes
- `POST /api/courses/:course_id/quizzes` (course staff)
- `GET /api/courses/:course_id/quizzes` (course member)
- `GET /api/courses/:course_id/quizzes/:quiz_id` (course member)
- `PUT /api/courses/:course_id/quizzes/:quiz_id` (course staff)
- `DELETE /api/courses/:course_id/quizzes/:quiz_id` (course staff)
- `POST /api/courses/:course_id/quizzes/:quiz_id/questions` (course staff)
- `DELETE /api/courses/:course_id/quizzes/:quiz_id/questions/:question_id` (course staff)

### Attempts
- `POST /api/courses/:course_id/quizzes/:quiz_id/attempts/start` (student)
- `POST /api/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/answers` (student, own attempt)
- `POST /api/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/submit` (student, own attempt)
- `GET /api/my/quizzes/attempts` (authenticated)

## How to run

1. Copy `tests/` into your project root (same folder as `app.js`).
2. Run:

```bash
node --test tests/api.quiz.functional.test.js
node --test tests/api.quiz.exploit.test.js
```

Or run both:

```bash
node --test tests/api.quiz.*.test.js
```

## Notes
- **Functional tests** validate the intended workflow.
- **Exploit tests** validate that a vulnerability is exploitable. If you have already fixed the issue, exploit tests should fail (that is expected).

