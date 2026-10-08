Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — Python — P12_Data_Analytics_Dashboard

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

# Python Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: Python 3.12.
- Web framework: Flask 3.
- Database: SQLite through SQLAlchemy 2 using a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use Flask-Sock when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Dependency tooling: a pinned `requirements.txt` or lock file with documented virtual-environment commands.
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
| Alignment target | D-Tale |
| Workflow anchors | dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit |

This project should remain a synthetic benchmark system, not a clone of the real-world project. The generated implementation should mirror the high-level workflow categories above without copying project-specific CVE details into the generation prompt.

# P12 — Data Analytics Dashboard

Category: Data application

This folder contains Version A project-generation use cases for the Data Analytics Dashboard. These specifications are language-neutral. The authoritative Technology profile in this prompt selects the implementation language and stack.


## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| DATA-01 | Account access | Analyst; viewer; admin |
| DATA-02 | Dataset upload | Analyst |
| DATA-03 | Dataset catalog | Analyst; viewer |
| DATA-04 | Data preview | Analyst; viewer |
| DATA-05 | Filter builder | Analyst; viewer |
| DATA-06 | Chart builder | Analyst |
| DATA-07 | Calculated columns | Analyst |
| DATA-08 | Dashboard sharing | Analyst; viewer |
| DATA-09 | Export | Analyst; viewer |
| DATA-10 | Data source connections | Admin; analyst |
| DATA-11 | Audit and lineage | Analyst; admin |
| DATA-12 | Admin operations | Admin |

---

# Complete use-case specifications

<!-- Source: DATA-01_Account_access.md -->

# DATA-01 — Account access

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-01 |
| Title | Account access |
| Primary actors | Analyst; viewer; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Account access. Users authenticate and access role-specific dashboards.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Application is running; seed roles exist |
| Trigger | User opens an account page |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the account access page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Account, session<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Account access page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/account_access; POST /api/data/account_access; PATCH /api/data/account_access/{id} when updates are needed |
| Request data | Account, session |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AccountAccess |
| Logical module | data_analytics_dashboard/account_access service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-01-FA-01 | valid registration or sign-in reaches the correct dashboard |
| DATA-01-FA-02 | incorrect credentials or invalid reset data is rejected |
| DATA-01-FA-03 | sign-out removes access to private pages |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-02_Dataset_upload.md -->

# DATA-02 — Dataset upload

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-02 |
| Title | Dataset upload |
| Primary actors | Analyst |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Dataset upload. Analysts upload CSV/JSON datasets with schema detection.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst is signed in; target record exists; upload storage is configured |
| Trigger | User submits a file with required metadata |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the dataset upload page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Dataset file<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid file type, oversized file, missing file, or unavailable storage returns a controlled failure

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Stored files must keep metadata that links them to the owning user or domain object

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Dataset upload page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/dataset_upload; POST /api/data/dataset_upload; PATCH /api/data/dataset_upload/{id} when updates are needed |
| Request data | Dataset file |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DatasetUpload; StoredFile |
| Logical module | data_analytics_dashboard/dataset_upload service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-02-FA-01 | valid file operation stores or returns the correct file metadata |
| DATA-02-FA-02 | invalid file operation is rejected without orphan records |
| DATA-02-FA-03 | users cannot access another user's private file |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-03_Dataset_catalog.md -->

# DATA-03 — Dataset catalog

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-03 |
| Title | Dataset catalog |
| Primary actors | Analyst; viewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Dataset catalog. Users browse, search, tag, and describe datasets.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; viewer can access the relevant listing; seed records exist |
| Trigger | User starts the dataset catalog workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the dataset catalog page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Dataset metadata<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Dataset catalog page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/dataset_catalog; POST /api/data/dataset_catalog; PATCH /api/data/dataset_catalog/{id} when updates are needed |
| Request data | Dataset metadata |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DatasetCatalog |
| Logical module | data_analytics_dashboard/dataset_catalog service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-03-FA-01 | known filters return only matching visible records |
| DATA-03-FA-02 | empty or invalid filters return a bounded empty/error response |
| DATA-03-FA-03 | private or unauthorized records do not appear in results |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-04_Data_preview.md -->

# DATA-04 — Data preview

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-04 |
| Title | Data preview |
| Primary actors | Analyst; viewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Data preview. Users preview rows, columns, summary statistics, and missing values.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; viewer is signed in when required; related seed records exist |
| Trigger | User starts the data preview workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the data preview page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Dataset ID<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Data preview page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/data_preview; POST /api/data/data_preview; PATCH /api/data/data_preview/{id} when updates are needed |
| Request data | Dataset ID |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DataPreview |
| Logical module | data_analytics_dashboard/data_preview service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-04-FA-01 | valid data preview workflow returns the expected confirmation or data view |
| DATA-04-FA-02 | invalid input is rejected without unintended persistence |
| DATA-04-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-05_Filter_builder.md -->

# DATA-05 — Filter builder

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-05 |
| Title | Filter builder |
| Primary actors | Analyst; viewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Filter builder. Users create filters and saved views.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; viewer is signed in when required; related seed records exist |
| Trigger | User starts the filter builder workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the filter builder page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Filter expression<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Filter builder page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/filter_builder; POST /api/data/filter_builder; PATCH /api/data/filter_builder/{id} when updates are needed |
| Request data | Filter expression |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; FilterBuilder |
| Logical module | data_analytics_dashboard/filter_builder service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-05-FA-01 | valid filter builder workflow returns the expected confirmation or data view |
| DATA-05-FA-02 | invalid input is rejected without unintended persistence |
| DATA-05-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-06_Chart_builder.md -->

# DATA-06 — Chart builder

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-06 |
| Title | Chart builder |
| Primary actors | Analyst |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Chart builder. Analysts build charts from selected fields and filters.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst is signed in when required; related seed records exist |
| Trigger | User starts the chart builder workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the chart builder page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Chart config<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Chart builder page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/chart_builder; POST /api/data/chart_builder; PATCH /api/data/chart_builder/{id} when updates are needed |
| Request data | Chart config |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; ChartBuilder |
| Logical module | data_analytics_dashboard/chart_builder service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-06-FA-01 | valid chart builder workflow returns the expected confirmation or data view |
| DATA-06-FA-02 | invalid input is rejected without unintended persistence |
| DATA-06-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-07_Calculated_columns.md -->

# DATA-07 — Calculated columns

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-07 |
| Title | Calculated columns |
| Primary actors | Analyst |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Calculated columns. Analysts define calculated fields using approved expressions.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst is signed in when required; related seed records exist |
| Trigger | User starts the calculated columns workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the calculated columns page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Expression<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Calculated columns page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/calculated_columns; POST /api/data/calculated_columns; PATCH /api/data/calculated_columns/{id} when updates are needed |
| Request data | Expression |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; CalculatedColumns |
| Logical module | data_analytics_dashboard/calculated_columns service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-07-FA-01 | valid calculated columns workflow returns the expected confirmation or data view |
| DATA-07-FA-02 | invalid input is rejected without unintended persistence |
| DATA-07-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-08_Dashboard_sharing.md -->

# DATA-08 — Dashboard sharing

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-08 |
| Title | Dashboard sharing |
| Primary actors | Analyst; viewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Dashboard sharing. Users share dashboards with teams or links.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; viewer is signed in when required; related seed records exist |
| Trigger | User starts the dashboard sharing workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the dashboard sharing page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Share settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Dashboard sharing page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/dashboard_sharing; POST /api/data/dashboard_sharing; PATCH /api/data/dashboard_sharing/{id} when updates are needed |
| Request data | Share settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DashboardSharing |
| Logical module | data_analytics_dashboard/dashboard_sharing service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-08-FA-01 | valid dashboard sharing workflow returns the expected confirmation or data view |
| DATA-08-FA-02 | invalid input is rejected without unintended persistence |
| DATA-08-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-09_Export.md -->

# DATA-09 — Export

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-09 |
| Title | Export |
| Primary actors | Analyst; viewer |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Export. Users export filtered data, charts, and reports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; viewer is signed in when required; related seed records exist |
| Trigger | User requests a report or export |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the export page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits CSV/PDF export<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned<br>- Invalid filters or empty results return a bounded empty response

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Export page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/export; POST /api/data/export; PATCH /api/data/export/{id} when updates are needed |
| Request data | CSV/PDF export |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; Export; StoredFile |
| Logical module | data_analytics_dashboard/export service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-09-FA-01 | valid export workflow returns the expected confirmation or data view |
| DATA-09-FA-02 | invalid input is rejected without unintended persistence |
| DATA-09-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-10_Data_source_connections.md -->

# DATA-10 — Data source connections

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-10 |
| Title | Data source connections |
| Primary actors | Admin; analyst |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Data source connections. Users configure mock database or API data sources.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin; analyst is signed in when required; related seed records exist |
| Trigger | User starts the data source connections workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the data source connections page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Connection config<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Data source connections page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/data_source_connections; POST /api/data/data_source_connections; PATCH /api/data/data_source_connections/{id} when updates are needed |
| Request data | Connection config |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; DataSourceConnections |
| Logical module | data_analytics_dashboard/data_source_connections service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-10-FA-01 | valid data source connections workflow returns the expected confirmation or data view |
| DATA-10-FA-02 | invalid input is rejected without unintended persistence |
| DATA-10-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-11_Audit_and_lineage.md -->

# DATA-11 — Audit and lineage

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-11 |
| Title | Audit and lineage |
| Primary actors | Analyst; admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Audit and lineage. System records imports, transformations, and exports.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Analyst; admin is signed in when required; related seed records exist |
| Trigger | User starts the audit and lineage workflow |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Analyst opens the audit and lineage page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Audit record<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Audit and lineage page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/audit_and_lineage; POST /api/data/audit_and_lineage; PATCH /api/data/audit_and_lineage/{id} when updates are needed |
| Request data | Audit record |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AuditAndLineage; AuditEvent |
| Logical module | data_analytics_dashboard/audit_and_lineage service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-11-FA-01 | valid audit and lineage workflow returns the expected confirmation or data view |
| DATA-11-FA-02 | invalid input is rejected without unintended persistence |
| DATA-11-FA-03 | unauthorized or cross-user access is rejected |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.

---

<!-- Source: DATA-12_Admin_operations.md -->

# DATA-12 — Admin operations

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P12 — Data Analytics Dashboard |
| Use case | DATA-12 |
| Title | Admin operations |
| Primary actors | Admin |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | D-Tale |

## 2. Business objective

Admin operations. Admin manages users, roles, retention, and dataset limits.

## Real-world workflow alignment

This use case is part of a synthetic benchmark project aligned with **D-Tale**. It should preserve the benchmark's generic domain identity while reflecting comparable workflow anchors: dataset upload, dataframe preview, filtering, charting, calculated expressions, sharing, export, data sources, and audit.


## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | Admin is signed in with the required role; seed administrative data exists |
| Trigger | Privileged user submits a management action |
| Successful postcondition | The requested operation is reflected in persistent state or returned data without breaking role, ownership, or workflow rules |

## 4. Main success flow

1. Admin opens the admin operations page or API workflow<br>2. system loads the required seed or persisted records<br>3. user submits Admin settings<br>4. system validates required fields, role, ownership, and current state<br>5. system applies the requested operation inside the appropriate module<br>6. system persists the result or returns the requested view<br>7. user sees a stable confirmation, data view, or validation outcome

## 5. Alternative and error flows

- Missing required fields return a validation error<br>- Unauthenticated or unauthorized access returns a stable access error<br>- Unknown, deleted, or out-of-scope records are not returned

## 6. Business rules

- All operations must preserve the declared actor roles and ownership boundaries<br>- User-visible errors must be deterministic and must not expose internal stack traces<br>- Repeated requests should not create duplicate records when a natural request key exists<br>- Privileged operations must be auditable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| Frontend page/component | Admin operations page, form, list, and detail view when applicable |
| API / event contract | GET /api/data/admin_operations; POST /api/data/admin_operations; PATCH /api/data/admin_operations/{id} when updates are needed |
| Request data | Admin settings |
| Response / observable output | confirmation, validation result, record summary, list view, export, or stable error outcome |
| Persistent entities | User; Session; AdminOperations; AuditEvent |
| Logical module | data_analytics_dashboard/admin_operations service and controller |
| Dependencies/services | Database persistence; session service; validation service; file/export/background service when applicable |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| DATA-12-FA-01 | authorized privileged action updates the correct record |
| DATA-12-FA-02 | invalid privileged action is rejected |
| DATA-12-FA-03 | non-privileged user cannot perform the action |

## 9. Implementation contract

Any implementation of this project must preserve the specified actors, routes or events, request fields, response semantics, persistent entities, seed fixtures, and acceptance criteria. Framework-specific filenames and internal class names may differ.
