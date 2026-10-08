# LMS-08 — Quiz lifecycle

> Version A — project-generation specification. This file must not cause the generator to create test files.

## Objective

Create and publish quizzes, accept timed attempts, and calculate objective scores.

## Actors

Instructor; student

## Main flow

1. The actor opens the relevant page and the client requests only data visible to that actor.
2. The actor supplies the required identifiers and fields.
3. The server authenticates the request, resolves referenced records, and checks role, ownership, and workflow state.
4. The server validates the input and performs the operation atomically when multiple records change.
5. The server returns the resulting resource or a stable confirmation; the client updates the page from that response.

## Alternative outcomes

- Missing or invalid fields return `422` with `{ "error": { "code", "message", "fields" } }`.
- No valid authentication returns `401`; an authenticated actor outside the permitted scope returns `403`.
- An unknown in-scope resource returns `404`; an invalid state transition or stale version returns `409`.
- Errors never expose stack traces, file paths, database messages, credentials, or hidden records.

## Interface and data contract

| Field | Specification |
| --- | --- |
| API / event contract | POST /api/courses/{courseId}/quizzes; PATCH /api/quizzes/{quizId}; POST /api/quizzes/{quizId}/publish; POST /api/quizzes/{quizId}/attempts; PUT /api/quiz-attempts/{attemptId}/answers; POST /api/quiz-attempts/{attemptId}/submit; GET /api/quiz-attempts/{attemptId}/result |
| Request data | Quiz, Question, QuizAttempt, Answer |
| Success response | JSON resource or collection appropriate to the endpoint; creation returns `201`, reads/updates/actions return `200`, deletion may return `204`. |
| Persistent entities | quiz settings and questions; attempt answers keyed by questionId |

## Business rules

- Students never receive answer keys before submission; attempt limits and time limits are server-controlled; submitted attempts are immutable.
- Collection endpoints use bounded pagination and deterministic ordering.
- The server derives actor identity and privileged fields from the authenticated session, never from client-supplied role or owner fields.
- Repeated create/action requests use a natural uniqueness rule or an explicit idempotency key where duplicate side effects are possible.

## Generation boundary

Implement the pages, routes/events, validation, persistence, seed data, and role-aware behavior described above. Do **not** generate test code, test directories, test dependencies, test configuration, or test documentation.
