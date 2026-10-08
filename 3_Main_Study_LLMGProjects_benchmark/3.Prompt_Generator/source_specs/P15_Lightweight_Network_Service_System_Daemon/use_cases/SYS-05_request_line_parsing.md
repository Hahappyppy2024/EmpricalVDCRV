# SYS-05 — Request-line parsing

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Incrementally parse a request line without assuming that one network read contains the complete request.

## Actors

Network client; daemon

## Main flow

1. Received bytes are appended to the connection input buffer up to the configured request-line limit.
2. The parser searches for a complete request-line terminator while preserving incomplete input.
3. When a complete line is available, the parser separates the method, request target, and protocol version.
4. Required tokens and supported syntax are validated.
5. Validated values are stored in the Request object and processing advances to header parsing.

## Alternative outcomes

- An incomplete line remains buffered until more bytes arrive or the connection times out.
- A line exceeding the configured maximum is rejected.
- Malformed or unsupported request syntax produces a deterministic client error.
- Invalid byte sequences are rejected without terminating the daemon.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Explicit byte range from Connection.input_buffer and parser offset. |
| Output fields | method, raw_target, protocol_version. |
| Success | Request-line state complete; unused buffered bytes remain available. |
| Failure | Stable request-parse error code used by response processing. |

## Business rules

- Parsing uses explicit lengths and must not rely on network input being NUL-terminated.
- Offsets and lengths are validated before indexing or copying.
- Configured request-line limits are enforced before buffer growth.
- Parser state is reset before processing a new request.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
