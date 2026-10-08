Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — PHP — P07_Cloud_File_Sharing_System

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
| Alignment target | Nextcloud Server |
| Workflow anchors | files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P07 — Cloud File-Sharing System

Category: Cloud service

This folder contains Version A project-generation use cases for the Cloud File-Sharing System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| FILE-01 | Account access | Visitor; user; admin |
| FILE-02 | File upload | User |
| FILE-03 | Folder management | User |
| FILE-04 | File download and preview | User; share recipient |
| FILE-05 | Sharing links | User |
| FILE-06 | Team spaces | User; admin |
| FILE-07 | Search | User |
| FILE-08 | Version history | User |
| FILE-09 | Trash and restore | User |
| FILE-10 | Storage quota | User; admin |
| FILE-11 | Audit log and exports | User; admin |
| FILE-12 | Admin console | Admin |

---

# Complete use-case specifications

<!-- Source: FILE-01_Account_access.md -->

# FILE-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-01 |
| Title | Account access |
| Primary actors | Visitor; user; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Account access. Users register, sign in, recover accounts, and manage sessions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/account_access; POST /api/file/account_access; PATCH /api/file/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | cloud_file_sharing_system/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| FILE-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| FILE-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-02_File_upload.md -->

# FILE-02 — File upload

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-02 |
| Title | File upload |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

File upload. Users upload files with names, folders, descriptions, and tags.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the file upload page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Multipart file, metadata<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | File upload page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/file_upload; POST /api/file/file_upload; PATCH /api/file/file_upload/{id} when updates are needed |
| Request data | Multipart file, metadata |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FileUpload; StoredFile |
| Logical module | cloud_file_sharing_system/file_upload service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-02-FA-01 | valid file operation stores or returns the correct file metadata |
| FILE-02-FA-02 | invalid file operation is rejected without orphan records |
| FILE-02-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-03_Folder_management.md -->

# FILE-03 — Folder management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-03 |
| Title | Folder management |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Folder management. Users create, rename, move, and delete folders.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the folder management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Folder path/ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Folder management page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/folder_management; POST /api/file/folder_management; PATCH /api/file/folder_management/{id} when updates are needed |
| Request data | Folder path/ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FolderManagement; AuditEvent |
| Logical module | cloud_file_sharing_system/folder_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-03-FA-01 | authorized privileged action updates the correct record |
| FILE-03-FA-02 | invalid privileged action is rejected |
| FILE-03-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-04_File_download_and_preview.md -->

# FILE-04 — File download and preview

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-04 |
| Title | File download and preview |
| Primary actors | User; share recipient |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

File download and preview. Authorized users download or preview stored files.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; share recipient is signed in; target record exists; upload storage is configured |
| Trigger | User starts the file download and preview workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the file download and preview page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits File ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | File download and preview page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/file_download_and_preview; POST /api/file/file_download_and_preview; PATCH /api/file/file_download_and_preview/{id} when updates are needed |
| Request data | File ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FileDownloadAndPreview; StoredFile |
| Logical module | cloud_file_sharing_system/file_download_and_preview service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-04-FA-01 | valid file operation stores or returns the correct file metadata |
| FILE-04-FA-02 | invalid file operation is rejected without orphan records |
| FILE-04-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-05_Sharing_links.md -->

# FILE-05 — Sharing links

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-05 |
| Title | Sharing links |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Sharing links. Users create public or private share links with expiration.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the sharing links workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the sharing links page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Share token, scope<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Tokens, links, and secrets must have explicit ownership and revocation behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Sharing links page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/sharing_links; POST /api/file/sharing_links; PATCH /api/file/sharing_links/{id} when updates are needed |
| Request data | Share token, scope |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; SharingLinks |
| Logical module | cloud_file_sharing_system/sharing_links service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-05-FA-01 | valid sharing links workflow returns the expected confirmation or data view |
| FILE-05-FA-02 | invalid input is rejected without unintended persistence |
| FILE-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-06_Team_spaces.md -->

# FILE-06 — Team spaces

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-06 |
| Title | Team spaces |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Team spaces. Teams maintain shared folders with role-based access.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the team spaces workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the team spaces page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Team membership<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Team spaces page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/team_spaces; POST /api/file/team_spaces; PATCH /api/file/team_spaces/{id} when updates are needed |
| Request data | Team membership |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; TeamSpaces |
| Logical module | cloud_file_sharing_system/team_spaces service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-06-FA-01 | valid team spaces workflow returns the expected confirmation or data view |
| FILE-06-FA-02 | invalid input is rejected without unintended persistence |
| FILE-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-07_Search.md -->

# FILE-07 — Search

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-07 |
| Title | Search |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Search. Users search files by name, owner, tag, and date.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User can access the relevant listing; seed records exist |
| Trigger | User submits filters or search text |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the search page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Search query<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Search page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/search; POST /api/file/search; PATCH /api/file/search/{id} when updates are needed |
| Request data | Search query |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Search |
| Logical module | cloud_file_sharing_system/search service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-07-FA-01 | known filters return only matching visible records |
| FILE-07-FA-02 | empty or invalid filters return a bounded empty/error response |
| FILE-07-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-08_Version_history.md -->

# FILE-08 — Version history

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-08 |
| Title | Version history |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Version history. Users upload new versions and restore previous versions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the version history workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the version history page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits File version<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Version history page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/version_history; POST /api/file/version_history; PATCH /api/file/version_history/{id} when updates are needed |
| Request data | File version |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; VersionHistory |
| Logical module | cloud_file_sharing_system/version_history service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-08-FA-01 | valid version history workflow returns the expected confirmation or data view |
| FILE-08-FA-02 | invalid input is rejected without unintended persistence |
| FILE-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-09_Trash_and_restore.md -->

# FILE-09 — Trash and restore

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-09 |
| Title | Trash and restore |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Trash and restore. Users delete files to trash and restore or purge them.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the trash and restore workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the trash and restore page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Trash item<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Trash and restore page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/trash_and_restore; POST /api/file/trash_and_restore; PATCH /api/file/trash_and_restore/{id} when updates are needed |
| Request data | Trash item |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; TrashAndRestore |
| Logical module | cloud_file_sharing_system/trash_and_restore service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-09-FA-01 | valid trash and restore workflow returns the expected confirmation or data view |
| FILE-09-FA-02 | invalid input is rejected without unintended persistence |
| FILE-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-10_Storage_quota.md -->

# FILE-10 — Storage quota

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-10 |
| Title | Storage quota |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Storage quota. System enforces per-user and team storage quotas.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User starts the storage quota workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the storage quota page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Quota counter<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Storage quota page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/storage_quota; POST /api/file/storage_quota; PATCH /api/file/storage_quota/{id} when updates are needed |
| Request data | Quota counter |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; StorageQuota |
| Logical module | cloud_file_sharing_system/storage_quota service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-10-FA-01 | valid storage quota workflow returns the expected confirmation or data view |
| FILE-10-FA-02 | invalid input is rejected without unintended persistence |
| FILE-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-11_Audit_log_and_exports.md -->

# FILE-11 — Audit log and exports

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-11 |
| Title | Audit log and exports |
| Primary actors | User; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Audit log and exports. Users export activity and admins review file activity logs.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User; admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the audit log and exports page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Audit export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Audit log and exports page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/audit_log_and_exports; POST /api/file/audit_log_and_exports; PATCH /api/file/audit_log_and_exports/{id} when updates are needed |
| Request data | Audit export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AuditLogAndExports; StoredFile; AuditEvent |
| Logical module | cloud_file_sharing_system/audit_log_and_exports service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-11-FA-01 | valid audit log and exports workflow returns the expected confirmation or data view |
| FILE-11-FA-02 | invalid input is rejected without unintended persistence |
| FILE-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: FILE-12_Admin_console.md -->

# FILE-12 — Admin console

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P07 — Cloud File-Sharing System |
| Use case | FILE-12 |
| Title | Admin console |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Nextcloud Server |

## 2. Business objective

Admin console. Admin manages users, storage policies, blocked file types, and retention.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Nextcloud Server**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: files, folders, shares, tags, versions, trash, activity logs, quota, team spaces, and admin storage policies.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin console page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin console page, form, list, and detail view when applicable |
| API / event contract | GET /api/file/admin_console; POST /api/file/admin_console; PATCH /api/file/admin_console/{id} when updates are needed |
| Request data | Admin settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminConsole; AuditEvent |
| Logical module | cloud_file_sharing_system/admin_console service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| FILE-12-FA-01 | authorized privileged action updates the correct record |
| FILE-12-FA-02 | invalid privileged action is rejected |
| FILE-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
