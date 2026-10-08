Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P02_Conference_Review_System

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
| Alignment target | Open Journal Systems / OJS |
| Workflow anchors | journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P02 — Conference Review System

Category: Academic workflow

This folder contains Version A project-generation use cases for the Conference Review System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| CONF-01 | Account access and recovery | Author; reviewer; chair; admin |
| CONF-02 | Conference phases | Chair |
| CONF-03 | Paper submission | Author |
| CONF-04 | Submission discovery | Author; chair |
| CONF-05 | Manuscript access | Author; reviewer; chair |
| CONF-06 | Reviewer assignment | Chair |
| CONF-07 | Reviewing | Reviewer |
| CONF-08 | Rebuttal | Author; reviewer |
| CONF-09 | Decision management | Chair |
| CONF-10 | Double-blind views | Author; reviewer; chair |
| CONF-11 | Bulk exports | Chair |
| CONF-12 | Frontend API integration and errors | User |

---

# Complete use-case specifications

<!-- Source: CONF-01_Account_access_and_recovery.md -->

# CONF-01 — Account access and recovery

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-01 |
| Title | Account access and recovery |
| Primary actors | Author; reviewer; chair; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Account access and recovery. Users authenticate and recover access in a seeded conference site.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the account access and recovery page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session, reset token<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access and recovery page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/account_access_and_recovery; POST /api/conf/account_access_and_recovery; PATCH /api/conf/account_access_and_recovery/{id} when updates are needed |
| Request data | Account, session, reset token |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccessAndRecovery |
| Logical module | conference_review_system/account_access_and_recovery service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| CONF-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| CONF-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-02_Conference_phases.md -->

# CONF-02 — Conference phases

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-02 |
| Title | Conference phases |
| Primary actors | Chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Conference phases. Chair configures submission, review, rebuttal, and decision phases.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Chair is signed in when required; related seed records exist |
| Trigger | User starts the conference phases workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Chair opens the conference phases page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Phase dates, status<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Conference phases page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/conference_phases; POST /api/conf/conference_phases; PATCH /api/conf/conference_phases/{id} when updates are needed |
| Request data | Phase dates, status |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ConferencePhases |
| Logical module | conference_review_system/conference_phases service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-02-FA-01 | valid conference phases workflow returns the expected confirmation or data view |
| CONF-02-FA-02 | invalid input is rejected without unintended persistence |
| CONF-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-03_Paper_submission.md -->

# CONF-03 — Paper submission

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-03 |
| Title | Paper submission |
| Primary actors | Author |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Paper submission. Authors submit title, abstract, metadata, and manuscript files.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author is signed in when required; related seed records exist |
| Trigger | User starts the paper submission workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the paper submission page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Submission form, PDF upload<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Paper submission page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/paper_submission; POST /api/conf/paper_submission; PATCH /api/conf/paper_submission/{id} when updates are needed |
| Request data | Submission form, PDF upload |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PaperSubmission |
| Logical module | conference_review_system/paper_submission service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-03-FA-01 | valid paper submission workflow returns the expected confirmation or data view |
| CONF-03-FA-02 | invalid input is rejected without unintended persistence |
| CONF-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-04_Submission_discovery.md -->

# CONF-04 — Submission discovery

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-04 |
| Title | Submission discovery |
| Primary actors | Author; chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Submission discovery. Users search and filter submissions visible to their role.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; chair can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the submission discovery page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search query, status filter<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Submission discovery page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/submission_discovery; POST /api/conf/submission_discovery; PATCH /api/conf/submission_discovery/{id} when updates are needed |
| Request data | Search query, status filter |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SubmissionDiscovery |
| Logical module | conference_review_system/submission_discovery service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-04-FA-01 | known filters return only matching visible records |
| CONF-04-FA-02 | empty or invalid filters return a bounded empty/error response |
| CONF-04-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-05_Manuscript_access.md -->

# CONF-05 — Manuscript access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-05 |
| Title | Manuscript access |
| Primary actors | Author; reviewer; chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Manuscript access. Authorized users download manuscripts and supplementary files.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; reviewer; chair is signed in when required; related seed records exist |
| Trigger | User starts the manuscript access workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the manuscript access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits File ID, submission ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Manuscript access page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/manuscript_access; POST /api/conf/manuscript_access; PATCH /api/conf/manuscript_access/{id} when updates are needed |
| Request data | File ID, submission ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ManuscriptAccess |
| Logical module | conference_review_system/manuscript_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-05-FA-01 | valid manuscript access workflow returns the expected confirmation or data view |
| CONF-05-FA-02 | invalid input is rejected without unintended persistence |
| CONF-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-06_Reviewer_assignment.md -->

# CONF-06 — Reviewer assignment

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-06 |
| Title | Reviewer assignment |
| Primary actors | Chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Reviewer assignment. Chair assigns reviewers and manages conflicts of interest.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Chair is signed in when required; related seed records exist |
| Trigger | User starts the reviewer assignment workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Chair opens the reviewer assignment page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Assignment, conflict record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Reviewer assignment page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/reviewer_assignment; POST /api/conf/reviewer_assignment; PATCH /api/conf/reviewer_assignment/{id} when updates are needed |
| Request data | Assignment, conflict record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ReviewerAssignment |
| Logical module | conference_review_system/reviewer_assignment service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-06-FA-01 | valid reviewer assignment workflow returns the expected confirmation or data view |
| CONF-06-FA-02 | invalid input is rejected without unintended persistence |
| CONF-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-07_Reviewing.md -->

# CONF-07 — Reviewing

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-07 |
| Title | Reviewing |
| Primary actors | Reviewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Reviewing. Reviewers submit scores, comments, confidence, and private notes.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Reviewer is signed in when required; related seed records exist |
| Trigger | User starts the reviewing workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Reviewer opens the reviewing page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Review form<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Reviewing page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/reviewing; POST /api/conf/reviewing; PATCH /api/conf/reviewing/{id} when updates are needed |
| Request data | Review form |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Reviewing |
| Logical module | conference_review_system/reviewing service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-07-FA-01 | valid reviewing workflow returns the expected confirmation or data view |
| CONF-07-FA-02 | invalid input is rejected without unintended persistence |
| CONF-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-08_Rebuttal.md -->

# CONF-08 — Rebuttal

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-08 |
| Title | Rebuttal |
| Primary actors | Author; reviewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Rebuttal. Authors submit rebuttals and reviewers view allowed responses.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; reviewer is signed in when required; related seed records exist |
| Trigger | User starts the rebuttal workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the rebuttal page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Rebuttal text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Rebuttal page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/rebuttal; POST /api/conf/rebuttal; PATCH /api/conf/rebuttal/{id} when updates are needed |
| Request data | Rebuttal text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Rebuttal |
| Logical module | conference_review_system/rebuttal service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-08-FA-01 | valid rebuttal workflow returns the expected confirmation or data view |
| CONF-08-FA-02 | invalid input is rejected without unintended persistence |
| CONF-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-09_Decision_management.md -->

# CONF-09 — Decision management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-09 |
| Title | Decision management |
| Primary actors | Chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Decision management. Chair records accept/reject decisions and notifies authors.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Chair is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Chair opens the decision management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Decision, notification<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Decision management page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/decision_management; POST /api/conf/decision_management; PATCH /api/conf/decision_management/{id} when updates are needed |
| Request data | Decision, notification |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DecisionManagement; AuditEvent |
| Logical module | conference_review_system/decision_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-09-FA-01 | authorized privileged action updates the correct record |
| CONF-09-FA-02 | invalid privileged action is rejected |
| CONF-09-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-10_Double_blind_views.md -->

# CONF-10 — Double-blind views

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-10 |
| Title | Double-blind views |
| Primary actors | Author; reviewer; chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Double-blind views. The system shows role-specific anonymized submission views.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; reviewer; chair is signed in when required; related seed records exist |
| Trigger | User starts the double-blind views workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the double-blind views page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Blind/non-blind view<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Double-blind views page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/double_blind_views; POST /api/conf/double_blind_views; PATCH /api/conf/double_blind_views/{id} when updates are needed |
| Request data | Blind/non-blind view |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DoubleBlindViews |
| Logical module | conference_review_system/double_blind_views service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-10-FA-01 | valid double-blind views workflow returns the expected confirmation or data view |
| CONF-10-FA-02 | invalid input is rejected without unintended persistence |
| CONF-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-11_Bulk_exports.md -->

# CONF-11 — Bulk exports

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-11 |
| Title | Bulk exports |
| Primary actors | Chair |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Bulk exports. Chair exports submissions, reviews, and decision summaries.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Chair is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Chair opens the bulk exports page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits CSV/PDF export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Bulk exports page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/bulk_exports; POST /api/conf/bulk_exports; PATCH /api/conf/bulk_exports/{id} when updates are needed |
| Request data | CSV/PDF export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; BulkExports; StoredFile |
| Logical module | conference_review_system/bulk_exports service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-11-FA-01 | valid bulk exports workflow returns the expected confirmation or data view |
| CONF-11-FA-02 | invalid input is rejected without unintended persistence |
| CONF-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CONF-12_Frontend_API_integration_and_errors.md -->

# CONF-12 — Frontend API integration and errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P02 — Conference Review System |
| Use case | CONF-12 |
| Title | Frontend API integration and errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Open Journal Systems / OJS |

## 2. Business objective

Frontend API integration and errors. UI presents validation, permission, and phase errors predictably.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Open Journal Systems / OJS**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: journal setup, manuscript submission, editorial phases, reviewer assignment, peer review, decisions, publication, and exports.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration and errors workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration and errors page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API error states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration and errors page, form, list, and detail view when applicable |
| API / event contract | GET /api/conf/frontend_api_integration_and_errors; POST /api/conf/frontend_api_integration_and_errors; PATCH /api/conf/frontend_api_integration_and_errors/{id} when updates are needed |
| Request data | API error states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegrationAndErrors |
| Logical module | conference_review_system/frontend_api_integration_and_errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CONF-12-FA-01 | valid frontend api integration and errors workflow returns the expected confirmation or data view |
| CONF-12-FA-02 | invalid input is rejected without unintended persistence |
| CONF-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
