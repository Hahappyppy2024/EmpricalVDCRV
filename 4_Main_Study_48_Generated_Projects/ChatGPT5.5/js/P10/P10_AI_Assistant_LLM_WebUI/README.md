# P10 AI Assistant / LLM WebUI

Localis AI is a complete offline-capable assistant interface built with Node.js 22, Express 5, SQLite, server-side sessions, SSE, and a vanilla JavaScript browser client. It implements all 12 specified use cases and 49 HTTP/SSE endpoints without paid services or external model accounts.

## Prerequisites and local run

- Node.js 22 LTS and npm
- Docker with Compose (optional)

```bash
cp .env.example .env
npm ci
npm run db:reset
npm start
```

Open <http://localhost:8080>. Database reset creates deterministic users, models, conversations, knowledge chunks, tools, masked credential metadata, chat runs, SSE events, citations, usage, audit events, settings, and moderation cases.

## Seed accounts

All accounts use `Password123!`.

| Role | Email |
| --- | --- |
| User | `user@example.test` |
| Other user | `other@example.test` |
| Administrator | `admin@example.test` |

`disabled@example.test` is the rejected-login fixture. Development password-reset requests return the local reset token; production mode returns only an accepted confirmation.

## Functional tests and coverage

```bash
npm run test:functional
npm run test:coverage
```

Each use case has one dedicated functional test file. Tests start the real Express server on an ephemeral TCP port and use a fresh seeded SQLite database. The coverage command includes `src/**`, excludes only the listener bootstrap `src/server.js`, and fails below 85% overall branch coverage.

Verified result: **12/12 passed, 86.80% branch coverage, 100% line coverage, and 98.06% function coverage**. See [COVERAGE_REPORT.md](COVERAGE_REPORT.md).

## Docker

```bash
docker compose up --build
```

The image pins Node.js 22 and persists SQLite in the `assistant-data` volume. Recreate the seed volume with:

```bash
docker compose down -v
docker compose up --build
```

## Offline adapters and request formats

- Chat uses a deterministic local model adapter. It creates a persistent user message, assistant message, completed run, usage record, audit event, SSE events, and optional knowledge citation atomically.
- `GET /api/chat-runs/:id/events` uses `text/event-stream` and replays persistent events in sequence; WebSocket is not required by this specification.
- Knowledge upload uses JSON fields `filename`, `mime_type`, and `content_base64`. Only bounded UTF-8 text/Markdown is accepted, then split deterministically into indexed chunks.
- Provider credentials store only a SHA-256 digest and last four characters. List, create, rotate, and delete responses never return the submitted secret or stored digest.
- Tools are allow-listed passive definitions. The registry cannot install or execute arbitrary code.
- The browser renders user and assistant messages through text nodes, not raw HTML.

## Use-case traceability

| ID | Workflow | Main routes | Functional test |
| --- | --- | --- | --- |
| AI-01 | Account access | registration, login/logout, password recovery | `ai01-account-access.test.js` |
| AI-02 | Conversations | create/list/read/edit/archive/delete | `ai02-conversation-management.test.js` |
| AI-03 | Prompt templates | list/create/edit/delete | `ai03-prompt-templates.test.js` |
| AI-04 | Model configuration | models, user settings, admin model update | `ai04-model-configuration.test.js` |
| AI-05 | Knowledge files | upload/metadata/delete | `ai05-knowledge-files.test.js` |
| AI-06 | Retrieval | collections, ingestion, search, conversation attachment | `ai06-retrieval-collections.test.js` |
| AI-07 | Tool registry | tool list, conversation enable/disable, admin update | `ai07-tool-registry.test.js` |
| AI-08 | API keys | masked list/create/rotate/delete | `ai08-api-keys.test.js` |
| AI-09 | Chat execution | message/run/cancel/SSE events | `ai09-chat-execution.test.js` |
| AI-10 | Sharing | create/revoke/public read-only share | `ai10-conversation-sharing.test.js` |
| AI-11 | Usage/audit | personal usage, admin usage and audit | `ai11-usage-audit.test.js` |
| AI-12 | Moderation/settings | allow-listed settings and case resolution | `ai12-moderation-settings.test.js` |

The main routes and deterministic model/retrieval logic are in `src/app.js`; authentication and SQLite sessions are in `src/auth.js`; schema, repository access, and fixtures are in `database/schema.sql`, `src/repository.js`, and `src/seed.js`.

## Behavioral guarantees

- Conversations, private templates, collections, files, credentials, chat runs, shares, and usage remain owner-scoped.
- Archived conversations reject edits and new messages; non-empty conversations reject deletion; versioned writes reject stale clients.
- Model temperature/token settings are bounded by enabled model definitions.
- Share tokens are stored as hashes and revoked or expired links return the same not-found response.
- Chat idempotency prevents repeated side effects, completed runs cannot be cancelled, and queued/running runs can be cancelled once.
- Administrative models, tools, settings, usage, audit, and moderation routes require the admin role.
- Errors use `{ "error": { "code", "message", "fields" } }` and do not expose stack traces, database errors, paths, or stored secrets.
