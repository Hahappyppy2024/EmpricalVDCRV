# SYS-06 — Header and request-metadata processing

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Parse request header fields incrementally and create the bounded metadata required by later request processing.

## Actors

Network client; daemon

## Main flow

1. After request-line parsing, complete header lines are consumed from the existing input buffer.
2. Each header field name and value is identified and checked against syntax and length rules.
3. Recognized fields such as Host, Connection, Content-Length, and Content-Type are converted into typed request metadata.
4. Other supported fields are stored in a bounded header collection.
5. An empty header line finalizes header processing and determines whether request-body processing is required.

## Alternative outcomes

- Requests exceeding total-header-byte or header-count limits are rejected.
- Malformed fields or conflicting request-framing metadata produce a deterministic error.
- Duplicate fields follow documented merge or reject rules based on field semantics.
- Incomplete header lines remain buffered for the next read.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Bytes after the parsed request line. |
| Runtime entities | HeaderCollection and typed Request metadata. |
| Success | Headers complete and request framing validated. |
| Limits | max_header_bytes, max_header_count, per-field length bounds. |

## Business rules

- Header parsing is byte-length aware.
- Total-header accounting includes consumed delimiters.
- Framing metadata is validated before body storage decisions.
- Header memory belongs to the current Request and is released with it.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
