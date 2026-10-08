Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P01_Learning_Management_System

Copy this entire document into the LLM for one project-generation run.

---

## System Prompt

You are an autonomous software implementation agent. Build one complete, integrated, runnable web application from the authoritative Technology profile, project specification, and full set of use cases in the user message.

Follow these rules exactly:

1. Implement every listed use case. No actor, route, event, field, entity, workflow state, seed fixture, or acceptance criterion is optional.
2. Use exactly the language, runtime, framework, database driver, authentication architecture, browser technology, real-time adapter, and dependency tooling in the Technology profile. Do not substitute a different stack.
3. Treat any language associated with the real-world alignment target only as metadata about that external reference project. It does not override the authoritative Technology profile.
4. Build a coherent multi-file application, not isolated snippets, pseudocode, a design document, or a test-only mock.
5. Preserve specified API paths, HTTP methods, event names, request fields, observable outcomes, roles, ownership relations, and workflow transitions. Internal filenames may differ when necessary.
6. Store persistent business data in SQLite and provide deterministic seed data for every actor, role, relationship, workflow state, and dependency required by the acceptance criteria.
7. Use deterministic local adapters for email, payment, object storage, model responses, repository integration, external HTTP retrieval, monitoring targets, and other external services. The application must run offline after dependencies are installed.
8. Provide prerequisites, dependency installation, environment configuration, database reset/seed, startup, and Docker commands in the README.
9. Do not generate functional tests, unit tests, integration tests, browser tests, security tests, benchmark oracle files, or attack scripts. Generate the application project only.
10. Do not copy code from the real-world alignment target. Implement the synthetic specification independently.
11. Do not omit or silently simplify requirements. When a minor implementation detail is unspecified, make a deterministic choice and document it without changing observable behavior.
12. Work in the current project directory. Create the required files, install dependencies, initialize the database, start or smoke-check the application when possible, and correct build or runtime failures before finishing.
13. In the final response, report the implemented modules, setup command, database command, startup command, Docker command, and smoke-check result. Do not paste the full source code into the response.

---

## User Prompt

# Project generation task

Build the complete web application defined below. The Technology profile is authoritative. The project specification and all use cases form one mandatory contract for a single integrated application.

This task is for application generation only. Do not generate tests, security-evaluation files, benchmark oracle files, or attack scripts.

## Authoritative Technology profile

# PHP Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: PHP 8.3.
- Web framework: Slim 4 with PSR-7 request and response handling.
- Database: SQLite through PDO using a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: when a use case requires WebSocket behavior, provide a Workerman-based local WebSocket process sharing the same SQLite data; otherwise use ordinary HTTP.
- Dependency tooling: Composer with exact direct dependency constraints and a committed `composer.lock`.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start commands that work without paid services or external accounts.
- Containerization: provide a Dockerfile and compose file for reproducible local execution.

## Required deliverables

- Complete multi-file application source
- SQLite schema, initialization/reset command, and deterministic seed fixtures
- Browser pages for every actor-facing workflow
- All specified HTTP routes and real-time events
- Locked or pinned dependency specification appropriate to the selected language
- `.env.example` with documented local values
- Dockerfile and compose file
- README with exact setup, seed-account, database, startup, Docker, and usage instructions
- README traceability table mapping every use-case ID to its main implementation files or routes

Do not treat the use cases as separate applications. Shared accounts, sessions, entities, relationships, and workflows must operate consistently across the project.

---

## Project specification

## Real-World Alignment

| Field | Value |
| --- | --- |
| Alignment target | Moodle / Moodle ecosystem |
| Workflow anchors | courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P01 — Learning Management System

Category: Educational application

This folder contains Version A project-generation use cases for the Learning Management System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| LMS-01 | Account access | Visitor; student; instructor; admin |
| LMS-02 | Course discovery | Student; instructor |
| LMS-03 | Enrollment | Student; instructor |
| LMS-04 | Course materials | Instructor; student |
| LMS-05 | Announcements | Instructor; student |
| LMS-06 | Discussion board | Student; instructor |
| LMS-07 | Assignment submission | Student; instructor |
| LMS-08 | Quiz lifecycle | Instructor; student |
| LMS-09 | Grades | Instructor; student |
| LMS-10 | Grade export | Instructor; admin |
| LMS-11 | Bulk course report | Admin |
| LMS-12 | Administrative API | Admin |
| LMS-13 | Frontend API integration | User |
| LMS-14 | Error responses | User |

---

# Complete use-case specifications

<!-- Source: LMS-01_Account_access.md -->

# LMS-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-01 |
| Title | Account access |
| Primary actors | Visitor; student; instructor; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Account access. Users register, sign in, recover accounts, and sign out.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Accounts, sessions, reset token<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/account_access; POST /api/lms/account_access; PATCH /api/lms/account_access/{id} when updates are needed |
| Request data | Accounts, sessions, reset token |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | learning_management_system/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| LMS-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| LMS-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-02_Course_discovery.md -->

# LMS-02 — Course discovery

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-02 |
| Title | Course discovery |
| Primary actors | Student; instructor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Course discovery. Students browse courses and filter by title, category, instructor, and semester.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Student; instructor can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Student opens the course discovery page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Course search, filter query<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Course discovery page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/course_discovery; POST /api/lms/course_discovery; PATCH /api/lms/course_discovery/{id} when updates are needed |
| Request data | Course search, filter query |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CourseDiscovery |
| Logical module | learning_management_system/course_discovery service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-02-FA-01 | known filters return only matching visible records |
| LMS-02-FA-02 | empty or invalid filters return a bounded empty/error response |
| LMS-02-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-03_Enrollment.md -->

# LMS-03 — Enrollment

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-03 |
| Title | Enrollment |
| Primary actors | Student; instructor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Enrollment. Students enroll in open courses and instructors view class rosters.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Student; instructor is signed in when required; related seed records exist |
| Trigger | User starts the enrollment workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Student opens the enrollment page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Enrollment request, course ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Enrollment page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/enrollment; POST /api/lms/enrollment; PATCH /api/lms/enrollment/{id} when updates are needed |
| Request data | Enrollment request, course ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Enrollment |
| Logical module | learning_management_system/enrollment service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-03-FA-01 | valid enrollment workflow returns the expected confirmation or data view |
| LMS-03-FA-02 | invalid input is rejected without unintended persistence |
| LMS-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-04_Course_materials.md -->

# LMS-04 — Course materials

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-04 |
| Title | Course materials |
| Primary actors | Instructor; student |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Course materials. Instructors upload course files and students download assigned materials.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Instructor; student is signed in when required; related seed records exist |
| Trigger | User starts the course materials workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Instructor opens the course materials page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits File upload, file metadata, download<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Course materials page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/course_materials; POST /api/lms/course_materials; PATCH /api/lms/course_materials/{id} when updates are needed |
| Request data | File upload, file metadata, download |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CourseMaterials |
| Logical module | learning_management_system/course_materials service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-04-FA-01 | valid course materials workflow returns the expected confirmation or data view |
| LMS-04-FA-02 | invalid input is rejected without unintended persistence |
| LMS-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-05_Announcements.md -->

# LMS-05 — Announcements

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-05 |
| Title | Announcements |
| Primary actors | Instructor; student |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Announcements. Instructors publish announcements and students view course updates.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Instructor; student is signed in when required; related seed records exist |
| Trigger | User starts the announcements workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Instructor opens the announcements page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Announcement title/body<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Announcements page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/announcements; POST /api/lms/announcements; PATCH /api/lms/announcements/{id} when updates are needed |
| Request data | Announcement title/body |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Announcements |
| Logical module | learning_management_system/announcements service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-05-FA-01 | valid announcements workflow returns the expected confirmation or data view |
| LMS-05-FA-02 | invalid input is rejected without unintended persistence |
| LMS-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-06_Discussion_board.md -->

# LMS-06 — Discussion board

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-06 |
| Title | Discussion board |
| Primary actors | Student; instructor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Discussion board. Course members post, reply, edit, and search discussion messages.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Student; instructor is signed in when required; related seed records exist |
| Trigger | User starts the discussion board workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Student opens the discussion board page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Posts, replies, rich text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Discussion board page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/discussion_board; POST /api/lms/discussion_board; PATCH /api/lms/discussion_board/{id} when updates are needed |
| Request data | Posts, replies, rich text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DiscussionBoard |
| Logical module | learning_management_system/discussion_board service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-06-FA-01 | valid discussion board workflow returns the expected confirmation or data view |
| LMS-06-FA-02 | invalid input is rejected without unintended persistence |
| LMS-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-07_Assignment_submission.md -->

# LMS-07 — Assignment submission

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-07 |
| Title | Assignment submission |
| Primary actors | Student; instructor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Assignment submission. Students upload submissions before deadlines and instructors review them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Student; instructor is signed in when required; related seed records exist |
| Trigger | User starts the assignment submission workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Student opens the assignment submission page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Assignment, uploaded file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Assignment submission page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/assignment_submission; POST /api/lms/assignment_submission; PATCH /api/lms/assignment_submission/{id} when updates are needed |
| Request data | Assignment, uploaded file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AssignmentSubmission |
| Logical module | learning_management_system/assignment_submission service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-07-FA-01 | valid assignment submission workflow returns the expected confirmation or data view |
| LMS-07-FA-02 | invalid input is rejected without unintended persistence |
| LMS-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-08_Quiz_lifecycle.md -->

# LMS-08 — Quiz lifecycle

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-08 |
| Title | Quiz lifecycle |
| Primary actors | Instructor; student |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Quiz lifecycle. Instructors create quizzes; students take quizzes and receive scores.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Instructor; student is signed in when required; related seed records exist |
| Trigger | User starts the quiz lifecycle workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Instructor opens the quiz lifecycle page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Questions, attempts, answers<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Quiz lifecycle page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/quiz_lifecycle; POST /api/lms/quiz_lifecycle; PATCH /api/lms/quiz_lifecycle/{id} when updates are needed |
| Request data | Questions, attempts, answers |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; QuizLifecycle |
| Logical module | learning_management_system/quiz_lifecycle service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-08-FA-01 | valid quiz lifecycle workflow returns the expected confirmation or data view |
| LMS-08-FA-02 | invalid input is rejected without unintended persistence |
| LMS-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-09_Grades.md -->

# LMS-09 — Grades

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-09 |
| Title | Grades |
| Primary actors | Instructor; student |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Grades. Instructors enter grades and students view only their own gradebook.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Instructor; student is signed in when required; related seed records exist |
| Trigger | User starts the grades workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Instructor opens the grades page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Grade item, score, feedback<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Grades page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/grades; POST /api/lms/grades; PATCH /api/lms/grades/{id} when updates are needed |
| Request data | Grade item, score, feedback |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Grades |
| Logical module | learning_management_system/grades service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-09-FA-01 | valid grades workflow returns the expected confirmation or data view |
| LMS-09-FA-02 | invalid input is rejected without unintended persistence |
| LMS-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-10_Grade_export.md -->

# LMS-10 — Grade export

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-10 |
| Title | Grade export |
| Primary actors | Instructor; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Grade export. Authorized staff export course grades and summary reports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Instructor; admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Instructor opens the grade export page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits CSV/PDF export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Grade export page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/grade_export; POST /api/lms/grade_export; PATCH /api/lms/grade_export/{id} when updates are needed |
| Request data | CSV/PDF export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; GradeExport; StoredFile |
| Logical module | learning_management_system/grade_export service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-10-FA-01 | valid grade export workflow returns the expected confirmation or data view |
| LMS-10-FA-02 | invalid input is rejected without unintended persistence |
| LMS-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-11_Bulk_course_report.md -->

# LMS-11 — Bulk course report

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-11 |
| Title | Bulk course report |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Bulk course report. Admin generates reports across courses, users, and enrollments.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the bulk course report page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Report filters<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Bulk course report page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/bulk_course_report; POST /api/lms/bulk_course_report; PATCH /api/lms/bulk_course_report/{id} when updates are needed |
| Request data | Report filters |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BulkCourseReport |
| Logical module | learning_management_system/bulk_course_report service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-11-FA-01 | valid bulk course report workflow returns the expected confirmation or data view |
| LMS-11-FA-02 | invalid input is rejected without unintended persistence |
| LMS-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-12_Administrative_API.md -->

# LMS-12 — Administrative API

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-12 |
| Title | Administrative API |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Administrative API. Admin manages users, roles, courses, enrollment states, and system settings.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the administrative api page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin endpoints<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Administrative API page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/administrative_api; POST /api/lms/administrative_api; PATCH /api/lms/administrative_api/{id} when updates are needed |
| Request data | Admin endpoints |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdministrativeApi; AuditEvent |
| Logical module | learning_management_system/administrative_api service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-12-FA-01 | authorized privileged action updates the correct record |
| LMS-12-FA-02 | invalid privileged action is rejected |
| LMS-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-13_Frontend_API_integration.md -->

# LMS-13 — Frontend API integration

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-13 |
| Title | Frontend API integration |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Frontend API integration. UI handles loading, validation, empty, and error states consistently.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API response states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/frontend_api_integration; POST /api/lms/frontend_api_integration; PATCH /api/lms/frontend_api_integration/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegration |
| Logical module | learning_management_system/frontend_api_integration service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-13-FA-01 | valid frontend api integration workflow returns the expected confirmation or data view |
| LMS-13-FA-02 | invalid input is rejected without unintended persistence |
| LMS-13-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: LMS-14_Error_responses.md -->

# LMS-14 — Error responses

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P01 — Learning Management System |
| Use case | LMS-14 |
| Title | Error responses |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Moodle / Moodle ecosystem |

## 2. Business objective

Error responses. Invalid requests return stable generic errors without exposing internals.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Moodle / Moodle ecosystem**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: courses, enrollments, assignments, quizzes, gradebook, forums, resources, role/capability checks, and plugin-like settings.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the error responses workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the error responses page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Error object<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Error responses page, form, list, and detail view when applicable |
| API / event contract | GET /api/lms/error_responses; POST /api/lms/error_responses; PATCH /api/lms/error_responses/{id} when updates are needed |
| Request data | Error object |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ErrorResponses |
| Logical module | learning_management_system/error_responses service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| LMS-14-FA-01 | valid error responses workflow returns the expected confirmation or data view |
| LMS-14-FA-02 | invalid input is rejected without unintended persistence |
| LMS-14-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
