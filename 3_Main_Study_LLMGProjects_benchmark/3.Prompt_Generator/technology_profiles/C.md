# C Technology Profile

This profile is authoritative for P15 — Lightweight Network Service / System Daemon.

- Language standard: C17.
- Target environment: POSIX/Linux on x86-64.
- Compiler toolchain: GCC 13+ or Clang 17+ with a committed `Makefile`.
- Build mode: compile with `-std=c17 -Wall -Wextra -Wpedantic`; document a release build and a debug build.
- Application architecture: one native executable implementing a lightweight TCP/HTTP-like system daemon. Do not replace the daemon with a high-level web framework, scripting runtime, or embedded application server.
- Networking: POSIX/BSD sockets using `socket`, `setsockopt`, `bind`, `listen`, `accept`, `recv`/`read`, `send`/`write`, and `close` as appropriate.
- I/O multiplexing: use `poll(2)` as the default portable event mechanism. Keep listener descriptors and client descriptors under explicit runtime ownership.
- Process lifecycle: run in the foreground by default for reproducible local execution; support clean handling of `SIGINT`, `SIGTERM`, and the project-specified reload signal. Signal handlers must only record signal-safe state changes; parsing, allocation, logging, socket creation, and cleanup must run in ordinary control flow.
- Configuration: load a local text configuration file. Provide a committed example configuration with deterministic localhost defaults, including listen address, port, document root, connection/request limits, timeout settings, and log paths.
- Request protocol: implement the HTTP-like request/response behavior required by the P15 use cases directly in C. Parsing must operate on explicit byte lengths and support partial network reads.
- Runtime state: use explicit C structures for server context, configuration, listener state, connections, requests, headers, request bodies, responses, resources, and runtime counters.
- Memory ownership: every dynamically allocated object, buffer, string, socket descriptor, file descriptor, and resource handle must have a clear owner and a defined release point. Shared or transferred ownership must be documented in code structure and README architecture notes.
- Buffer handling: all input, header, body, path, and response storage must use explicit capacities and lengths. Size calculations must be checked before allocation, growth, indexing, copying, and transmission.
- Filesystem access: serve only resources resolved beneath the configured document root. Use deterministic local files supplied with the project; no external object storage or network dependency is required.
- Logging: provide local access and error logs, with a documented stderr/stdout fallback. Log records must remain single logical records and must not expose raw process memory addresses or secret configuration values.
- Persistence: no SQL database is required for P15 unless a use case explicitly requires persistent business data. Runtime connection/request state remains in process memory; configuration, static resources, and logs use the local filesystem.
- Authentication and browser UI: do not add authentication, sessions, HTML administration pages, or a browser application unless explicitly required by a P15 use case. This project is a native systems-software daemon, not a CRUD web application.
- External services: the generated project must run offline after toolchain dependencies are installed. Do not require paid services, cloud accounts, third-party APIs, or external databases.
- Local execution: provide exact commands to build, clean, run with the sample configuration, send a benign local request, reload configuration, and stop the daemon.
- Debugging support: provide a documented debug build suitable for local runtime inspection. Do not make sanitizers or coverage tools mandatory for normal project generation.
- Dependency policy: prefer the C standard library and POSIX APIs. Avoid third-party runtime libraries unless a use case cannot reasonably be implemented without one; any added dependency must be justified and reproducibly installable.
- Containerization: provide a Dockerfile and compose file that build the C executable in a builder stage and run it in a minimal Linux runtime image with the sample configuration and document root.
- Documentation: the README must describe architecture, build prerequisites, build commands, configuration fields, startup/shutdown/reload commands, sample local request usage, Docker commands, and a traceability table mapping every P15 use-case ID to its principal source files/functions.
- Generation boundary: generate the application project only. Do not generate functional tests, unit tests, integration tests, security tests, benchmark oracle files, attack scripts, coverage scripts, or test documentation.
