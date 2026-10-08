# P15 — Lightweight Network Service / System Daemon — use cases

This project replaces the previous P15 Healthcare Appointment / Patient Portal project.

The project is a compact **C-based native systems-software project** for a POSIX/Linux environment, aligned conceptually with a lightweight network service / HTTP daemon such as lighttpd.

These specifications define the software to generate; **do not generate functional, integration, browser, security, coverage, or other test files**. Acceptance and testing are performed separately.

## Project-level requirements

- Primary implementation language: **C**.
- Produce one coherent executable daemon rather than independent demonstration programs.
- Include ordinary build instructions (for example, a Makefile) and a minimal sample configuration/document root.
- Implement daemon lifecycle, configuration, TCP listening, client connections, parsing, bounded input handling, resource lookup, response transmission, cleanup, logging, and reload behavior.
- Use explicit ownership for sockets, buffers, requests, responses, resources, and configuration objects.
- Support partial network reads and writes.
- Do **not** generate tests, test directories, test dependencies, coverage scripts, or test documentation.

## Use-case index

| ID | Use case | Actors | Interface/event contract |
| --- | --- | --- | --- |
| SYS-01 | Daemon startup and shutdown | Operator; operating system | CLI invocation; SIGTERM; SIGINT; process exit status |
| SYS-02 | Configuration loading and validation | Operator | Configuration file; supported command-line overrides |
| SYS-03 | Listener and socket management | Daemon; operating system | TCP socket create/bind/listen/accept |
| SYS-04 | Client connection lifecycle | Network client; daemon | Accepted socket; connection-state transitions; connection close |
| SYS-05 | Request-line parsing | Network client; daemon | Incoming connection bytes → method, target path, protocol version |
| SYS-06 | Header and request-metadata processing | Network client; daemon | Header lines → normalized request metadata |
| SYS-07 | Static resource lookup and access | Network client; daemon | Request path → document-root resource |
| SYS-08 | Request body and input-buffer handling | Network client; daemon | Validated body length; partial socket reads; bounded request body |
| SYS-09 | Response construction and transmission | Daemon; network client | Request outcome/resource → status, headers, body, socket writes |
| SYS-10 | Timeouts, error isolation, and cleanup | Daemon; operating system | Timer/error event → terminal connection cleanup |
| SYS-11 | Access logging and runtime diagnostics | Operator; daemon | Request/lifecycle events → access log, error log, runtime counters |
| SYS-12 | Graceful configuration reload | Operator; daemon | SIGHUP/reload request → validated configuration replacement |

## Cross-use-case consistency

- All use cases must share the same daemon implementation and common runtime structures.
- Reuse consistent `Config`, `ServerContext`, `Listener`, `Connection`, `Request`, `Response`, `Resource`, and buffer abstractions where applicable.
- Resource ownership must remain explicit across creation, transfer, use, and cleanup.
- Configured size and connection limits must be enforced consistently.
- Complex work must not execute directly inside asynchronous signal handlers.
