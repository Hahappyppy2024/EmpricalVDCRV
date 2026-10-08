Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — JavaScript — P05_Content_Management_System

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

# JavaScript Technology Profile

This profile is authoritative for this implementation.

- Language: JavaScript (ECMAScript modules).
- Runtime: Node.js 22 LTS.
- Web framework: Express 5.
- Database: SQLite using `better-sqlite3` through a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use the `ws` package when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Package tooling: npm with a committed `package-lock.json` and pinned direct dependency versions.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start scripts that work without paid services or external accounts.
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
| Alignment target | Strapi |
| Workflow anchors | content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P05 — Content Management System

Category: Content management

This folder contains Version A project-generation use cases for the Content Management System. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| CMS-01 | Account access | Author; editor; admin |
| CMS-02 | Content authoring | Author; editor |
| CMS-03 | Rich text editor | Author; editor |
| CMS-04 | Media library | Author; editor |
| CMS-05 | Publishing workflow | Author; editor |
| CMS-06 | Public site | Visitor |
| CMS-07 | Comments | Visitor; moderator |
| CMS-08 | Page templates | Editor; admin |
| CMS-09 | User and role management | Admin |
| CMS-10 | Plugin/settings panel | Admin |
| CMS-11 | Import/export | Admin |
| CMS-12 | Frontend API integration and errors | User |

---

# Complete use-case specifications

<!-- Source: CMS-01_Account_access.md -->

# CMS-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-01 |
| Title | Account access |
| Primary actors | Author; editor; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Account access. Users authenticate and access role-specific dashboards.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/account_access; POST /api/cms/account_access; PATCH /api/cms/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | content_management_system/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| CMS-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| CMS-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-02_Content_authoring.md -->

# CMS-02 — Content authoring

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-02 |
| Title | Content authoring |
| Primary actors | Author; editor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Content authoring. Authors create drafts with title, body, tags, and status.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; editor is signed in when required; related seed records exist |
| Trigger | User starts the content authoring workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the content authoring page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Article content<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Content authoring page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/content_authoring; POST /api/cms/content_authoring; PATCH /api/cms/content_authoring/{id} when updates are needed |
| Request data | Article content |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ContentAuthoring |
| Logical module | content_management_system/content_authoring service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-02-FA-01 | valid content authoring workflow returns the expected confirmation or data view |
| CMS-02-FA-02 | invalid input is rejected without unintended persistence |
| CMS-02-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-03_Rich_text_editor.md -->

# CMS-03 — Rich text editor

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-03 |
| Title | Rich text editor |
| Primary actors | Author; editor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Rich text editor. Users format article content and preview rendered output.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; editor is signed in when required; related seed records exist |
| Trigger | User starts the rich text editor workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the rich text editor page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Rich text body<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Rich text editor page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/rich_text_editor; POST /api/cms/rich_text_editor; PATCH /api/cms/rich_text_editor/{id} when updates are needed |
| Request data | Rich text body |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; RichTextEditor |
| Logical module | content_management_system/rich_text_editor service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-03-FA-01 | valid rich text editor workflow returns the expected confirmation or data view |
| CMS-03-FA-02 | invalid input is rejected without unintended persistence |
| CMS-03-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-04_Media_library.md -->

# CMS-04 — Media library

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-04 |
| Title | Media library |
| Primary actors | Author; editor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Media library. Users upload, list, rename, and insert media assets.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; editor is signed in; target record exists; upload storage is configured |
| Trigger | User starts the media library workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the media library page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Image/file upload<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Media library page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/media_library; POST /api/cms/media_library; PATCH /api/cms/media_library/{id} when updates are needed |
| Request data | Image/file upload |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; MediaLibrary; StoredFile |
| Logical module | content_management_system/media_library service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-04-FA-01 | valid file operation stores or returns the correct file metadata |
| CMS-04-FA-02 | invalid file operation is rejected without orphan records |
| CMS-04-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-05_Publishing_workflow.md -->

# CMS-05 — Publishing workflow

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-05 |
| Title | Publishing workflow |
| Primary actors | Author; editor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Publishing workflow. Articles move through draft, review, scheduled, and published states.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Author; editor is signed in when required; related seed records exist |
| Trigger | User starts the publishing workflow workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Author opens the publishing workflow page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Publication status<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid state transition is rejected without partial persistence

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Publishing workflow page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/publishing_workflow; POST /api/cms/publishing_workflow; PATCH /api/cms/publishing_workflow/{id} when updates are needed |
| Request data | Publication status |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PublishingWorkflow |
| Logical module | content_management_system/publishing_workflow service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-05-FA-01 | valid publishing workflow workflow returns the expected confirmation or data view |
| CMS-05-FA-02 | invalid input is rejected without unintended persistence |
| CMS-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-06_Public_site.md -->

# CMS-06 — Public site

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-06 |
| Title | Public site |
| Primary actors | Visitor |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Public site. Visitors browse published pages, posts, categories, and search results.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Visitor is signed in when required; related seed records exist |
| Trigger | User starts the public site workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the public site page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Public routes<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Public site page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/public_site; POST /api/cms/public_site; PATCH /api/cms/public_site/{id} when updates are needed |
| Request data | Public routes |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PublicSite |
| Logical module | content_management_system/public_site service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-06-FA-01 | valid public site workflow returns the expected confirmation or data view |
| CMS-06-FA-02 | invalid input is rejected without unintended persistence |
| CMS-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-07_Comments.md -->

# CMS-07 — Comments

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-07 |
| Title | Comments |
| Primary actors | Visitor; moderator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Comments. Visitors comment on posts and moderators approve or remove comments.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Visitor; moderator is signed in when required; related seed records exist |
| Trigger | User starts the comments workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Visitor opens the comments page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Comment text<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Comments page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/comments; POST /api/cms/comments; PATCH /api/cms/comments/{id} when updates are needed |
| Request data | Comment text |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Comments |
| Logical module | content_management_system/comments service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-07-FA-01 | valid comments workflow returns the expected confirmation or data view |
| CMS-07-FA-02 | invalid input is rejected without unintended persistence |
| CMS-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-08_Page_templates.md -->

# CMS-08 — Page templates

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-08 |
| Title | Page templates |
| Primary actors | Editor; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Page templates. Editors assign templates and manage navigation menus.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Editor; admin is signed in when required; related seed records exist |
| Trigger | User starts the page templates workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Editor opens the page templates page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Template ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Page templates page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/page_templates; POST /api/cms/page_templates; PATCH /api/cms/page_templates/{id} when updates are needed |
| Request data | Template ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PageTemplates |
| Logical module | content_management_system/page_templates service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-08-FA-01 | valid page templates workflow returns the expected confirmation or data view |
| CMS-08-FA-02 | invalid input is rejected without unintended persistence |
| CMS-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-09_User_and_role_management.md -->

# CMS-09 — User and role management

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-09 |
| Title | User and role management |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

User and role management. Admin manages users, roles, and author/editor permissions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the user and role management page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Role assignment<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | User and role management page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/user_and_role_management; POST /api/cms/user_and_role_management; PATCH /api/cms/user_and_role_management/{id} when updates are needed |
| Request data | Role assignment |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; UserAndRoleManagement; AuditEvent |
| Logical module | content_management_system/user_and_role_management service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-09-FA-01 | authorized privileged action updates the correct record |
| CMS-09-FA-02 | invalid privileged action is rejected |
| CMS-09-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-10_Plugin_settings_panel.md -->

# CMS-10 — Plugin/settings panel

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-10 |
| Title | Plugin/settings panel |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Plugin/settings panel. Admin configures site settings, widgets, and integrations.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in when required; related seed records exist |
| Trigger | User starts the plugin/settings panel workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the plugin/settings panel page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Settings payload<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Plugin/settings panel page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/plugin_settings_panel; POST /api/cms/plugin_settings_panel; PATCH /api/cms/plugin_settings_panel/{id} when updates are needed |
| Request data | Settings payload |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; PluginSettingsPanel |
| Logical module | content_management_system/plugin_settings_panel service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-10-FA-01 | valid plugin/settings panel workflow returns the expected confirmation or data view |
| CMS-10-FA-02 | invalid input is rejected without unintended persistence |
| CMS-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-11_Import_export.md -->

# CMS-11 — Import/export

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-11 |
| Title | Import/export |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Import/export. Admin imports sample content and exports site data.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the import/export page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Import file, export file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Import/export page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/import_export; POST /api/cms/import_export; PATCH /api/cms/import_export/{id} when updates are needed |
| Request data | Import file, export file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ImportExport; StoredFile |
| Logical module | content_management_system/import_export service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-11-FA-01 | valid file operation stores or returns the correct file metadata |
| CMS-11-FA-02 | invalid file operation is rejected without orphan records |
| CMS-11-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: CMS-12_Frontend_API_integration_and_errors.md -->

# CMS-12 — Frontend API integration and errors

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P05 — Content Management System |
| Use case | CMS-12 |
| Title | Frontend API integration and errors |
| Primary actors | User |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Strapi |

## 2. Business objective

Frontend API integration and errors. UI handles validation, preview, missing pages, and permission errors.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **Strapi**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: content types, entries, rich text, media library, public API delivery, roles, permissions, admin settings, and import/export.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | User is signed in when required; related seed records exist |
| Trigger | User starts the frontend api integration and errors workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. User opens the frontend api integration and errors page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits API response states<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Frontend API integration and errors page, form, list, and detail view when applicable |
| API / event contract | GET /api/cms/frontend_api_integration_and_errors; POST /api/cms/frontend_api_integration_and_errors; PATCH /api/cms/frontend_api_integration_and_errors/{id} when updates are needed |
| Request data | API response states |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FrontendApiIntegrationAndErrors |
| Logical module | content_management_system/frontend_api_integration_and_errors service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| CMS-12-FA-01 | valid frontend api integration and errors workflow returns the expected confirmation or data view |
| CMS-12-FA-02 | invalid input is rejected without unintended persistence |
| CMS-12-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
