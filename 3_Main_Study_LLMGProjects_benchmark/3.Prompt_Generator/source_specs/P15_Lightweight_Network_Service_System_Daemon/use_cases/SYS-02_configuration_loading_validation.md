# SYS-02 — Configuration loading and validation

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Load typed settings from a configuration file, validate them, apply supported overrides, and produce a coherent runtime configuration.

## Actors

Operator

## Main flow

1. The daemon reads the configured text file containing supported key/value settings.
2. Recognized settings are parsed into typed values such as listen address, port, document root, request-size limits, connection limits, timeout values, log paths, and worker settings.
3. Each value is checked against documented type, range, and relationship rules.
4. Supported command-line overrides are applied after file values have been parsed.
5. A validated Config object is published to startup or reload logic.

## Alternative outcomes

- Unreadable or malformed configuration causes validation to fail with a clear operator-facing diagnostic.
- Unknown mandatory settings are rejected rather than silently ignored.
- Out-of-range numeric settings are rejected.
- During reload, an invalid candidate configuration is rejected while the currently active configuration remains in use.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Configuration file and documented CLI overrides. |
| Core fields | listen_address, port, document_root, max_connections, max_request_line, max_header_bytes, max_body_bytes, idle_timeout, log paths. |
| Success | Validated Config structure with explicit ownership of dynamic values. |
| Failure | Configuration error without partially activating invalid settings. |

## Business rules

- Every numeric setting has explicit minimum and maximum bounds.
- Absent, empty, malformed, and out-of-range values are distinguished.
- Path settings are normalized and validated before becoming active.
- A replacement configuration is fully validated before publication.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
