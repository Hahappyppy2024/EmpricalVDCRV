# SYS-07 — Static resource lookup and access

> Version A — project-generation specification. This file defines application behavior for project generation. Do not generate functional, integration, browser, security, coverage, or other test files.

## Objective

Resolve an accepted request target to an eligible resource under the configured document root and provide it to response processing.

## Actors

Network client; daemon

## Main flow

1. The daemon separates the request path from optional query metadata.
2. The path is decoded and normalized according to documented request-target rules.
3. The normalized relative path is resolved against the configured document root.
4. The daemon verifies that the resulting resource remains within the permitted root and satisfies the supported resource policy.
5. An eligible regular file is opened and its metadata is read.
6. The Resource object is handed to response construction.

## Alternative outcomes

- A missing resource results in the documented not-found response.
- A directory request follows the configured index-file or rejection policy.
- A disallowed resource type or path outside the document root is rejected.
- Filesystem errors are translated into stable client results without exposing unrelated local filesystem details.

## Interface and data contract

| Field | Specification |
| --- | --- |
| Input | Validated request target and Config.document_root. |
| Entity | Resource {file descriptor/handle, size, modification metadata, content type}. |
| Success | Opened resource with explicit ownership transferred to response processing. |
| Failure | Mapped client status plus internal diagnostic code. |

## Business rules

- Client-controlled text is not treated as an unrestricted filesystem path.
- Normalization and root-boundary checks precede file content serving.
- Resource handles are closed on every terminal path.
- File-size values are checked before transfer or allocation arithmetic.

## Generation boundary

Implement the C source code, build files, configuration support, runtime logic, socket handling, data structures, resource ownership, error handling, logging, and documentation required by this use case and the shared P15 project.

Do **not** generate test code, test directories, test dependencies, test configuration, coverage scripts, test fixtures, or test documentation.
