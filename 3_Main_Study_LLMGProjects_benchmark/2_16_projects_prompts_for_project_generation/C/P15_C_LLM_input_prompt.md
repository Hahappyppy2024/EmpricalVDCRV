Please generate a complete, executable, and runnable project based on the following content.

# Direct LLM Input Prompt — C — P15_Lightweight_Network_Service_System_Daemon

Copy this entire document into the LLM for one project-generation run.

---

## System Prompt

You are an autonomous software implementation agent. Build one complete, integrated, runnable native systems-software project from the authoritative Technology profile, project specification, and full set of use cases in the user message.

Follow these rules exactly:

1. Implement every listed use case. No actor, command, configuration key, field, entity, workflow state, seed fixture, or acceptance criterion is optional.
2. Use exactly the language, runtime, build system, operating-system APIs, database driver, network architecture, concurrency model, and dependency tooling in the Technology profile. Do not substitute a web framework, managed runtime, or another language.
3. Treat any technology associated with the real-world alignment target only as metadata about external reference systems. It does not override the authoritative Technology profile.
4. Build a coherent multi-file native application, not isolated snippets, pseudocode, a design document, or a test-only mock.
5. Preserve specified CLI flags, configuration keys, TCP commands, request fields, observable outcomes, roles, namespace relations, lifecycle states, and transitions. Internal filenames may differ when necessary.
6. Store persistent business data in SQLite and provide deterministic seed data for every principal, token role, namespace grant, record state, and dependency required by the acceptance criteria.
7. Use deterministic local behavior only. The service must build and run without paid services, cloud accounts, containers at runtime, or external network access after dependencies are installed.
8. Provide prerequisites, dependency installation, configure/build, database reset/seed, foreground startup, daemon startup, CLI use, shutdown, and Docker commands in the README.
9. Do not generate functional tests, unit tests, integration tests, fuzzers, security tests, benchmark oracle files, or attack scripts. Generate the application project only.
10. Do not copy code from a real-world alignment target. Implement the synthetic specification independently.
11. Do not omit or silently simplify requirements. When a minor implementation detail is unspecified, make a deterministic choice and document it without changing observable behavior.
12. Work in the current project directory. Create the required files, install dependencies when possible, configure and build the project, initialize the database, start or smoke-check the daemon and CLI when possible, and correct build or runtime failures before finishing.
13. Compile with strict warnings and treat project-source warnings as errors. Sanitizer builds must be available as documented optional build presets, but tests are not part of this generation task.
14. In the final response, report the implemented modules, dependency command, configure/build command, database command, foreground startup command, daemon command, Docker command, and smoke-check result. Do not paste the full source code into the response.

---

## User Prompt

# Project generation task

Build the complete native service defined below. The Technology profile is authoritative. The project specification and all use cases form one mandatory contract for a single integrated application.

This task is for application generation only. Do not generate tests, security-evaluation files, benchmark oracle files, fuzzers, or attack scripts.

## Authoritative Technology profile

# C Technology Profile

This profile is authoritative for this implementation.

- Language: ISO C17.
- Target platform: POSIX/Linux, x86-64 baseline.
- Compiler: GCC 13 or Clang 17; project code must compile with `-std=c17 -Wall -Wextra -Wpedantic -Werror`.
- Build system: CMake 3.25 or newer with out-of-source builds and CTest enabled for later benchmark use; do not generate tests in this task.
- Service architecture: one long-running native daemon named `netd` plus an operator/client executable named `netctl`.
- Network transport: IPv4 TCP using POSIX sockets; default bind address `127.0.0.1` and default port `9090`; use `poll` or Linux `epoll` for connection readiness.
- Application protocol: bounded newline-delimited UTF-8/ASCII commands with deterministic `OK <code> ...` and `ERR <code> <message>` responses. Reject embedded NUL bytes, overlong lines, invalid field counts, and invalid Base64.
- Concurrency: a bounded pthread worker pool; per-connection parser and authentication state; no detached untracked worker threads.
- Persistence: SQLite 3 through the native C API, with prepared statements, transactions, schema versioning, WAL mode, foreign keys, and a bounded busy timeout.
- Authentication: deterministic seeded opaque access tokens stored only as SHA-256 hashes; use OpenSSL libcrypto for hashing and constant-time digest comparison. Tokens have client, monitoring, or administrator roles and may be revoked or expired.
- Configuration: a documented strict `key=value` file; unknown keys, duplicate keys, malformed values, and out-of-range values are startup or reload errors.
- Logging: bounded line-oriented structured logs with stable event and outcome codes; credentials and record values must never be logged.
- Dependencies: pthreads, SQLite3, and OpenSSL libcrypto. Use system packages with minimum supported versions documented in README and Dockerfile; do not add a large application framework.
- Local execution: provide deterministic database initialization/reset/seed commands and foreground/daemon startup commands that require no external accounts.
- Containerization: provide a multi-stage Dockerfile and compose file for reproducible build and local execution. The container runs in foreground mode and stores SQLite/log data in mounted writable volumes.

## Required deliverables

- Complete multi-file C source and headers
- Root `CMakeLists.txt` and organized `src/`, `include/`, and `tools/` directories
- `netd` daemon and `netctl` client/status executable
- Strict configuration parser and `.env.example` only for Docker/compose variable substitution
- SQLite schema, schema versioning, reset/seed command, and deterministic fixtures
- All specified CLI flags, TCP protocol commands, signals, lifecycle behavior, and stable response/error codes
- Example `netd.conf` with documented local values
- Multi-stage Dockerfile and compose file
- README with exact prerequisites, dependency, configure/build, reset/seed, foreground, daemon, client, shutdown, Docker, and usage commands
- README protocol table and traceability table mapping every use-case ID to its main source files, functions, commands, and configuration keys
- Optional CMake sanitizer presets for AddressSanitizer and UndefinedBehaviorSanitizer; do not generate tests in this task

Do not treat the use cases as separate programs. Configuration, principals, token roles, namespaces, records, SQLite state, worker threads, metrics, logs, signals, and lifecycle behavior must operate consistently across one integrated daemon.

---

## Project specification

## Real-World Alignment

| Field | Value |
| --- | --- |
| Alignment target | Redis and traditional POSIX/Linux daemon operating patterns |
| Workflow anchors | foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown |

This project remains a synthetic benchmark system, not a clone of Redis or another daemon. The generated implementation should mirror only the high-level operating patterns above and must not include external CVE or CWE details.

# P15 — Lightweight Network Service / System Daemon

Category: Systems software / native network service

This folder contains Version A project-generation use cases for a compact C-based POSIX/Linux daemon. The authoritative Technology profile in this prompt selects the implementation language, build system, service protocol, and dependencies.

## Use Case Index

| Use case | Title | Primary actors |
| --- | --- | --- |
| NETD-01 | Configuration and startup | Operator |
| NETD-02 | Service lifecycle control | Operator; service manager |
| NETD-03 | Client authentication and session | Client; administrator |
| NETD-04 | Record creation | Authenticated client |
| NETD-05 | Record retrieval and bounded listing | Authenticated client |
| NETD-06 | Conditional update and deletion | Authenticated client |
| NETD-07 | Record expiry processing | Authenticated client; operator |
| NETD-08 | Concurrent client handling and limits | Multiple authenticated clients; operator |
| NETD-09 | Live configuration reload | Operator |
| NETD-10 | Health and operational metrics | Monitoring client; operator |
| NETD-11 | Structured audit logging | Operator; administrator |
| NETD-12 | Graceful shutdown and restart recovery | Operator; authenticated client |

---

# Complete use-case specifications

<!-- Source: NETD-01_Configuration_and_startup.md -->

# NETD-01 — Configuration and startup

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-01 |
| Title | Configuration and startup |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The operator validates configuration, initializes persistent state, and starts the service in foreground or daemon mode.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The binary is built; the configuration file and writable runtime directories exist |
| Trigger | The operator invokes netd with a configuration path and execution mode |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Operator invokes `netd --config <path> --foreground` or `netd --config <path> --daemon`<br>2. the service parses the complete configuration before opening sockets<br>3. the service validates address, port, database path, log path, PID path, limits, and timeouts<br>4. the service initializes or migrates the SQLite schema<br>5. the service acquires the PID-file lock and binds the configured TCP listener<br>6. the service reports a deterministic ready state

## 5. Alternative and error flows

- unknown or malformed configuration keys produce a stable startup error<br>- an unwritable database, log, or PID path prevents startup<br>- an occupied port or existing live PID lock prevents a second instance

## 6. Business rules

- configuration validation is atomic: no listener is opened after a validation failure<br>- default values are documented and deterministic<br>- daemon mode must not report success until initialization completes

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | CLI: `netd --config PATH [--foreground|--daemon]`; config file: `netd.conf` |
| Request / input data | bind_address, port, database_path, log_path, pid_file, worker_count, idle_timeout_seconds, max_clients, max_command_bytes |
| Response / observable output | exit status; ready/error log record; PID file |
| Persistent entities | SchemaVersion; ServiceInstance |
| Logical module | config; startup; storage initialization |
| Dependencies/services | POSIX process/filesystem APIs; SQLite |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-01-FA-01 | a valid foreground configuration reaches the ready state and accepts connections |
| NETD-01-FA-02 | invalid configuration exits nonzero without opening a listener or leaving a PID file |
| NETD-01-FA-03 | a second instance using the same live PID file or port is rejected |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-02_Service_lifecycle.md -->

# NETD-02 — Service lifecycle control

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-02 |
| Title | Service lifecycle control |
| Primary actors | Operator; service manager |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The operator starts, inspects, and stops one well-identified daemon instance using documented lifecycle commands and signals.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | A valid configuration exists; the runtime directory is writable |
| Trigger | The operator starts the daemon or sends a supported lifecycle signal |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Operator starts the daemon<br>2. the daemon writes its PID only after successful initialization<br>3. the operator uses `netctl status` to inspect PID and readiness<br>4. the service manager sends SIGTERM for a normal stop<br>5. the daemon stops accepting new clients, completes bounded cleanup, removes its PID file, and exits zero

## 5. Alternative and error flows

- a stale PID file is detected and replaced only after confirming the process is absent<br>- status returns a stable not-running result when no live instance exists<br>- unsupported signals follow documented default handling

## 6. Business rules

- the PID file must identify the running instance and cannot silently refer to another process<br>- normal stop is bounded by the configured shutdown timeout<br>- foreground and daemon modes expose equivalent service behavior

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | CLI: `netctl status --config PATH`; signals: SIGTERM and SIGINT |
| Request / input data | configuration path; target PID from the locked PID file |
| Response / observable output | running/not-running status; clean exit status |
| Persistent entities | ServiceInstance |
| Logical module | process lifecycle; status client |
| Dependencies/services | POSIX signals; PID-file locking |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-02-FA-01 | status identifies a ready running instance |
| NETD-02-FA-02 | SIGTERM produces a clean bounded stop and removes the PID file |
| NETD-02-FA-03 | a stale PID file does not block a later valid start |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-03_Client_authentication.md -->

# NETD-03 — Client authentication and session

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-03 |
| Title | Client authentication and session |
| Primary actors | Client; administrator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

A client authenticates with a seeded token before using protected commands and may explicitly end the session.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is ready; deterministic client and administrator token records are seeded |
| Trigger | A client opens a TCP connection and submits an AUTH command |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Client connects to the configured TCP listener<br>2. the daemon assigns a connection identifier and unauthenticated state<br>3. client sends `AUTH <token>` within the authentication timeout<br>4. the daemon verifies the token hash and active role<br>5. the connection enters authenticated client or administrator state<br>6. client sends `QUIT` or closes the connection

## 5. Alternative and error flows

- missing, unknown, revoked, or expired tokens return a stable authentication error<br>- protected commands before AUTH are rejected<br>- authentication timeout closes the idle unauthenticated connection

## 6. Business rules

- plaintext tokens are never stored in SQLite or written to logs<br>- one connection has one authenticated identity<br>- failed authentication does not create a session record

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP commands: `AUTH <token>`, `QUIT` |
| Request / input data | token; connection identifier |
| Response / observable output | `OK AUTH <role>` or stable `ERR AUTH_*` response |
| Persistent entities | Principal; AccessToken; ClientSession |
| Logical module | protocol session; authentication repository |
| Dependencies/services | OpenSSL SHA-256; SQLite |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-03-FA-01 | a valid seeded token establishes the expected role |
| NETD-03-FA-02 | an invalid or revoked token never enables protected commands |
| NETD-03-FA-03 | QUIT closes the session without affecting other clients |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-04_Record_creation.md -->

# NETD-04 — Record creation

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-04 |
| Title | Record creation |
| Primary actors | Authenticated client |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

An authenticated client creates a namespaced key/value record with an optional expiry and receives its initial version.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The client is authenticated; the namespace is allowed for the principal |
| Trigger | The client submits a PUT command for a new key |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Client sends `PUT <namespace> <key> <ttl_seconds> <base64_value>`<br>2. the daemon validates field count, lengths, encoding, namespace access, and TTL bounds<br>3. the daemon begins a SQLite transaction<br>4. the daemon creates the record at version 1 and calculates expires_at when TTL is nonzero<br>5. the transaction commits<br>6. the daemon returns the version and expiry

## 5. Alternative and error flows

- an existing key returns a stable already-exists response<br>- invalid Base64, lengths, namespace, or TTL are rejected without persistence<br>- database failure rolls back the transaction

## 6. Business rules

- namespace plus key is unique<br>- values are decoded only after size bounds are checked<br>- a successful response is sent only after commit

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP command: `PUT <namespace> <key> <ttl_seconds> <base64_value>` |
| Request / input data | namespace; key; TTL seconds; Base64 value |
| Response / observable output | `OK CREATED <version> <expires_at|NONE>` |
| Persistent entities | Record; NamespaceGrant |
| Logical module | command parser; record service; record repository |
| Dependencies/services | SQLite transactions; Base64 codec |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-04-FA-01 | a valid new key is persisted at version 1 and can be read later |
| NETD-04-FA-02 | duplicate and malformed requests are rejected without changing the original record |
| NETD-04-FA-03 | a committed TTL is returned consistently with stored expiry |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-05_Record_retrieval_and_listing.md -->

# NETD-05 — Record retrieval and bounded listing

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-05 |
| Title | Record retrieval and bounded listing |
| Primary actors | Authenticated client |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

A client retrieves one visible record or lists a bounded page of keys in an authorized namespace.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The client is authenticated; visible seeded and created records exist |
| Trigger | The client submits GET or LIST |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Client sends `GET <namespace> <key>` or `LIST <namespace> <prefix> <limit> <after_key|->`<br>2. the daemon validates namespace access and bounds<br>3. expired records are excluded<br>4. GET returns one record and version; LIST orders keys lexicographically<br>5. LIST returns at most the requested bounded limit plus a continuation key<br>6. the daemon returns a deterministic response

## 5. Alternative and error flows

- unknown or expired keys return not-found<br>- an unauthorized namespace returns access-denied<br>- invalid limit or continuation key returns a validation error

## 6. Business rules

- listing limit cannot exceed the configured maximum<br>- clients never observe records from unauthorized namespaces<br>- pagination order and continuation behavior are stable

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP commands: `GET ...`, `LIST ...` |
| Request / input data | namespace; key or prefix; limit; continuation key |
| Response / observable output | record payload/version or bounded ordered key page |
| Persistent entities | Record; NamespaceGrant |
| Logical module | record query service; pagination |
| Dependencies/services | SQLite indexed queries |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-05-FA-01 | GET returns the correct visible unexpired value and version |
| NETD-05-FA-02 | LIST returns only authorized prefix matches in deterministic order |
| NETD-05-FA-03 | expired, missing, and unauthorized records are not disclosed |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-06_Conditional_update_and_delete.md -->

# NETD-06 — Conditional update and deletion

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-06 |
| Title | Conditional update and deletion |
| Primary actors | Authenticated client |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

A client updates or deletes a record only when its expected version matches the current version.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The client is authenticated and can modify an existing record |
| Trigger | The client submits UPDATE or DELETE with expected_version |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Client reads the current record version<br>2. client sends `UPDATE <namespace> <key> <expected_version> <ttl_seconds> <base64_value>` or `DELETE <namespace> <key> <expected_version>`<br>3. the daemon validates access and input<br>4. the repository performs the change inside a transaction conditioned on the expected version<br>5. UPDATE increments the version; DELETE removes the row<br>6. the daemon commits and returns the new version or deletion confirmation

## 5. Alternative and error flows

- a stale expected version returns a conflict and leaves data unchanged<br>- unknown or expired keys return not-found<br>- invalid value or TTL is rejected before the transaction

## 6. Business rules

- no lost update is allowed when clients use expected versions<br>- version increments exactly once per committed update<br>- failed conditional operations have no side effects

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP commands: `UPDATE ...`, `DELETE ...` |
| Request / input data | namespace; key; expected version; optional TTL and Base64 value |
| Response / observable output | updated version, deletion confirmation, or stable conflict |
| Persistent entities | Record |
| Logical module | record mutation service; optimistic concurrency |
| Dependencies/services | SQLite conditional transactions |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-06-FA-01 | a matching version performs exactly one update or delete |
| NETD-06-FA-02 | a stale version returns conflict with no data change |
| NETD-06-FA-03 | a successful update increments the version and preserves namespace ownership |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-07_Expiry_processing.md -->

# NETD-07 — Record expiry processing

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-07 |
| Title | Record expiry processing |
| Primary actors | Authenticated client; operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

Records with TTLs become unavailable at deterministic expiry times and are removed by bounded maintenance work.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is ready; records with and without TTLs exist |
| Trigger | A record reaches expires_at or the maintenance interval fires |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. The daemon calculates expiry using wall-clock epoch seconds<br>2. GET and LIST treat a reached expiry as unavailable immediately<br>3. the maintenance worker selects an ordered bounded batch of expired records<br>4. the worker deletes the batch in one transaction<br>5. the worker records aggregate deletion metrics<br>6. later maintenance intervals continue until no expired batch remains

## 5. Alternative and error flows

- a zero TTL creates a non-expiring record<br>- a maintenance database error rolls back the batch and retries at the next interval<br>- clock values before a record's expiry do not remove it

## 6. Business rules

- read-time visibility and maintenance deletion use the same expiry comparison<br>- each maintenance transaction is bounded by configuration<br>- maintenance must not block client handling beyond the configured database busy timeout

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | Configuration: expiry_scan_interval_seconds and expiry_batch_size; protocol observations through GET/LIST/STATS |
| Request / input data | stored expires_at and current time |
| Response / observable output | not-found after expiry; expired-record metrics |
| Persistent entities | Record; MaintenanceRun |
| Logical module | expiry worker; record query filter |
| Dependencies/services | POSIX clock; SQLite |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-07-FA-01 | a TTL record becomes unavailable when its expiry is reached |
| NETD-07-FA-02 | a non-expiring record remains available across maintenance cycles |
| NETD-07-FA-03 | a failed cleanup batch does not partially delete records |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-08_Concurrent_client_handling.md -->

# NETD-08 — Concurrent client handling and limits

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-08 |
| Title | Concurrent client handling and limits |
| Primary actors | Multiple authenticated clients; operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The daemon serves multiple clients without mixing session state and enforces configured connection and command limits.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is ready with max_clients and worker_count configured |
| Trigger | Several clients connect and issue overlapping commands |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. The accept loop admits connections up to max_clients<br>2. each connection owns an independent parser buffer and authenticated session<br>3. worker threads process complete commands<br>4. database busy handling follows a bounded retry timeout<br>5. responses are written to the originating connection in request order<br>6. connection and command counters are updated

## 5. Alternative and error flows

- connections above max_clients receive a deterministic busy response or are closed as documented<br>- idle clients are closed after idle_timeout_seconds<br>- an incomplete command waits only within byte and idle limits

## 6. Business rules

- authentication and buffers are never shared between connections<br>- one slow client cannot make an unbounded allocation<br>- the process remains responsive when a database write is temporarily busy

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP listener; configuration: max_clients, worker_count, idle_timeout_seconds, max_command_bytes |
| Request / input data | concurrent connection streams |
| Response / observable output | per-client ordered responses; busy/timeout outcomes |
| Persistent entities | ClientSession; ServiceMetric |
| Logical module | accept loop; worker pool; connection state |
| Dependencies/services | pthreads; poll or epoll; SQLite busy timeout |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-08-FA-01 | two authenticated clients receive only their own command responses |
| NETD-08-FA-02 | the configured connection limit is enforced deterministically |
| NETD-08-FA-03 | oversized or idle connections are closed without stopping healthy clients |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-09_Live_configuration_reload.md -->

# NETD-09 — Live configuration reload

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-09 |
| Title | Live configuration reload |
| Primary actors | Operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The operator reloads supported runtime settings with SIGHUP without interrupting established service behavior.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is running; the operator can edit the configuration file |
| Trigger | The operator writes a new configuration and sends SIGHUP |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Operator atomically replaces the configuration file<br>2. operator sends SIGHUP<br>3. the signal handler records a reload request using an async-signal-safe mechanism<br>4. the main control loop parses and validates the entire new configuration<br>5. reloadable values are swapped as one configuration generation<br>6. the daemon logs the successful generation number

## 5. Alternative and error flows

- invalid new configuration is rejected and the prior generation remains active<br>- non-reloadable address, port, database, or PID paths produce a stable restart-required result<br>- multiple rapid SIGHUP requests coalesce without corrupting state

## 6. Business rules

- no parsing, allocation, logging, or SQLite work occurs directly in the signal handler<br>- reload is all-or-nothing<br>- current connections continue under documented timeout and limit semantics

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | Signal: SIGHUP; config file; log event `CONFIG_RELOAD` |
| Request / input data | new configuration generation |
| Response / observable output | successful generation or stable rejection/restart-required log |
| Persistent entities | ConfigurationGeneration |
| Logical module | signal bridge; configuration reload |
| Dependencies/services | POSIX signals; atomic file replacement |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-09-FA-01 | valid reloadable settings become active without restarting the listener |
| NETD-09-FA-02 | invalid configuration leaves all prior settings active |
| NETD-09-FA-03 | a non-reloadable change is clearly reported as restart-required |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-10_Health_and_metrics.md -->

# NETD-10 — Health and operational metrics

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-10 |
| Title | Health and operational metrics |
| Primary actors | Monitoring client; operator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

Authorized monitoring obtains bounded health and counter data without receiving record contents or credentials.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is ready; a seeded monitoring or administrator token exists |
| Trigger | A monitoring client authenticates and submits PING, HEALTH, or STATS |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. Monitoring client authenticates<br>2. client sends PING, HEALTH, or STATS<br>3. PING confirms protocol responsiveness<br>4. HEALTH checks listener state and performs a bounded SQLite probe<br>5. STATS snapshots counters and current limits<br>6. the daemon returns a deterministic machine-readable line without secret or record payload data

## 5. Alternative and error flows

- an unauthenticated request is rejected<br>- a failed database probe returns degraded health with a stable reason code<br>- unknown metric names are not accepted

## 6. Business rules

- metrics expose counts and durations, not tokens, values, or raw command bodies<br>- health work is time-bounded<br>- counter snapshots are thread-safe

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | TCP commands: `PING`, `HEALTH`, `STATS` |
| Request / input data | authenticated monitoring session |
| Response / observable output | PONG; health state; bounded counters |
| Persistent entities | ServiceMetric; HealthProbe |
| Logical module | health service; metrics registry |
| Dependencies/services | monotonic clock; atomic counters; SQLite probe |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-10-FA-01 | PING returns PONG for a ready daemon |
| NETD-10-FA-02 | HEALTH reports degraded when the database probe fails |
| NETD-10-FA-03 | STATS never includes plaintext tokens or stored record values |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-11_Audit_logging.md -->

# NETD-11 — Structured audit logging

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-11 |
| Title | Structured audit logging |
| Primary actors | Operator; administrator |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The daemon records deterministic operational and administrative audit events while excluding credentials and stored values.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The configured audit-log destination is writable |
| Trigger | A startup, authentication outcome, mutation, reload, limit event, or shutdown occurs |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. The service constructs an event with timestamp, event type, connection ID, principal ID when known, namespace/key identifiers when applicable, and outcome code<br>2. the logger serializes one line per event<br>3. sensitive command fields are omitted<br>4. the line is appended under a process-level log lock<br>5. flush policy follows configuration<br>6. log-write failures increment a metric and are reported to stderr in foreground mode

## 5. Alternative and error flows

- read-only GET payloads are never logged<br>- a log reopen request closes and reopens the configured path between complete records<br>- an unwritable required audit log prevents startup when strict_audit is enabled

## 6. Business rules

- tokens, Base64 values, and decoded values are never logged<br>- each event is one bounded line<br>- event type and outcome codes are stable for automated analysis

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | Audit file; signal-assisted log reopen; STATS log-error counter |
| Request / input data | structured event fields |
| Response / observable output | one bounded audit line |
| Persistent entities | AuditEvent |
| Logical module | structured logger; redaction policy |
| Dependencies/services | POSIX file APIs; mutex |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-11-FA-01 | successful and failed authentication emit distinct redacted events |
| NETD-11-FA-02 | record mutation logs identifiers and outcome but not the value |
| NETD-11-FA-03 | log reopening preserves complete non-interleaved records |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---

<!-- Source: NETD-12_Graceful_shutdown_and_recovery.md -->

# NETD-12 — Graceful shutdown and restart recovery

> Version A — project generation specification.

## 1. Identity and scope

| Field | Defined value |
| --- | --- |
| Project | P15 — Lightweight Network Service / System Daemon |
| Use case | NETD-12 |
| Title | Graceful shutdown and restart recovery |
| Primary actors | Operator; authenticated client |
| Specification status | READY_FOR_GENERATION |
| Real-world alignment target | Redis and traditional POSIX/Linux daemon operating patterns |

## 2. Business objective

The daemon stops predictably, preserves committed records, and recovers cleanly on the next start.

## Real-world workflow alignment

This use case belongs to a synthetic benchmark aligned with **Redis and traditional POSIX/Linux daemon operating patterns**. It preserves the benchmark's independent identity while reflecting comparable workflow anchors: foreground and daemon modes, configuration loading, TCP client sessions, bounded command parsing, authenticated state operations, expiry processing, concurrent clients, live reload, operational metrics, audit logs, and graceful shutdown. It must not copy source code or vulnerability details from an external project.

## 3. Preconditions and trigger

| Field | Defined behavior |
| --- | --- |
| Preconditions | The daemon is running with active or idle clients and committed SQLite data |
| Trigger | The operator sends SIGTERM or SIGINT |
| Successful postcondition | The requested operation reaches a deterministic observable state without violating identity, namespace, persistence, concurrency, or lifecycle rules. |

## 4. Main success flow

1. The signal bridge notifies the control loop<br>2. the daemon marks itself stopping and closes the listener<br>3. new connections are refused while complete in-flight commands receive a bounded completion window<br>4. worker threads stop and join<br>5. SQLite statements and connections are finalized<br>6. logs are flushed, the PID file is removed, and the process exits<br>7. on restart, schema validation and SQLite recovery complete before readiness<br>8. previously committed unexpired records remain available

## 5. Alternative and error flows

- commands exceeding the shutdown deadline receive a stable shutdown outcome or have their connection closed<br>- an interrupted prior process leaves no assumption that the PID alone is live<br>- database integrity failure prevents readiness and produces a deterministic startup error

## 6. Business rules

- no successful response may describe an uncommitted mutation<br>- shutdown ordering prevents worker access after storage teardown<br>- restart never silently discards or recreates committed user data

## 7. Planned interface and data contract

| Field | Defined design |
| --- | --- |
| CLI / daemon interface | Signals: SIGTERM/SIGINT; startup recovery; status and audit output |
| Request / input data | shutdown request and existing persistent state |
| Response / observable output | bounded exit; clean later readiness; preserved committed records |
| Persistent entities | Record; ServiceInstance; SchemaVersion |
| Logical module | shutdown coordinator; storage lifecycle; startup recovery |
| Dependencies/services | POSIX signals and threads; SQLite |

## 8. Functional acceptance criteria

| Criterion ID | Expected behavior |
| --- | --- |
| NETD-12-FA-01 | SIGTERM closes the listener and exits within the configured deadline |
| NETD-12-FA-02 | records acknowledged before shutdown remain available after restart |
| NETD-12-FA-03 | a corrupt or incompatible database prevents a false ready state |

## 9. Implementation contract

Any implementation must preserve the specified actors, CLI commands, protocol commands, configuration keys, inputs, observable outcomes, persistent entities, deterministic seed fixtures, and acceptance criteria. Internal filenames and function names may differ when necessary.

---
