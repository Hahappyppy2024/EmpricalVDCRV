# HarborMail — P13 Mail Server Admin Console

Executable offline PHP 8.3 / Slim 4 application implementing MAIL-01 through MAIL-12. Mail, account, session, domain, quarantine and audit data are stored through PDO SQLite. Sending and administrative effects are deterministic local simulations; no external SMTP service or account is required.

## Requirements and local run

- PHP 8.3 with `pdo_sqlite`
- Composer 2
- Optional: Xdebug 3 for branch/path coverage
- Optional: Docker with Compose

```bash
composer install
php bin/reset-database.php
php -S 0.0.0.0:8080 -t public public/router.php
```

Open <http://localhost:8080/>. `.env.example` documents local configuration overrides.

## Seed accounts

Every account uses `Password123!`.

| Actor | Email | Role/scope |
|---|---|---|
| Alice | `alice@example.test` | mailbox user for `example.test` |
| Bob | `bob@other.test` | mailbox user for `other.test` |
| Dana | `admin@example.test` | domain admin for `example.test` |
| Omar | `other-admin@other.test` | domain admin for `other.test` |
| Avery | `platform@example.test` | platform admin |
| Disabled fixture | `disabled@example.test` | disabled mailbox user |

Alice owns mailbox `1`; Bob owns mailbox `2`. Domain IDs are `1` (`example.test`) and `2` (`other.test`). The paired domains intentionally make cross-mailbox and cross-domain isolation reproducible.

## Explicit request formats

The source specification leaves binary/import transport unspecified. This implementation makes the contract deterministic:

- Attachment upload: JSON fields `filename`, `mime`, and `content_base64`; maximum decoded size is 1 MiB.
- Contact import: JSON field `csv`, containing a `name,email` header and rows.
- Draft create/update: `recipients` is a JSON array; optional `idempotency_key` prevents duplicate draft creation.

## Functional tests and coverage

Each business use case has exactly one primary HTTP functional test file. Every test file gets a fresh seeded temporary SQLite database.

```bash
php bin/run-functional-tests.php
XDEBUG_MODE=coverage php bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

Verified result: `12 use-case files, 166 assertions, 0 failures.` PHP 8.3.6 with Xdebug 3.2.0 measured line `56/56 (100%)`, branch `207/219 (94.52%)`, and path `104/182 (57.14%)`. The summarizer exits non-zero below the required 85% branch threshold.

## Docker

```bash
docker compose up --build
```

The console is served at <http://localhost:8080>; the `mail-data` volume retains SQLite data.

## Use-case traceability

| ID | Workflow | Main API contract | Functional test |
|---|---|---|---|
| MAIL-01 | Account access | `/api/auth/login`, `/logout`, `/password-reset-*` | `tests/Functional/MAIL01AccountAccessTest.php` |
| MAIL-02 | Mailbox overview | `/api/mail/folders`, `/api/mail/messages` | `tests/Functional/MAIL02MailboxOverviewTest.php` |
| MAIL-03 | Message compose | `/api/mail/drafts`, draft update/send/delete | `tests/Functional/MAIL03MessageComposeTest.php` |
| MAIL-04 | Message reading | message read/update/move/delete | `tests/Functional/MAIL04MessageReadingTest.php` |
| MAIL-05 | Attachments | draft attachment upload/delete and content read | `tests/Functional/MAIL05AttachmentHandlingTest.php` |
| MAIL-06 | Contacts | `/api/contacts` search/create/update/delete | `tests/Functional/MAIL06ContactManagementTest.php` |
| MAIL-07 | Filters/rules | `/api/mail/rules` CRUD and reorder | `tests/Functional/MAIL07FiltersRulesTest.php` |
| MAIL-08 | Domains | admin domains, aliases and mailbox quota | `tests/Functional/MAIL08DomainManagementTest.php` |
| MAIL-09 | Quarantine | list, release and delete | `tests/Functional/MAIL09QuarantineTest.php` |
| MAIL-10 | Audit logs | `GET /api/admin/audit-events` | `tests/Functional/MAIL10AdminAuditLogsTest.php` |
| MAIL-11 | Import/export | contact import/export and domain mailbox CSV | `tests/Functional/MAIL11ImportExportTest.php` |
| MAIL-12 | Settings | `GET/PATCH /api/mail/settings` | `tests/Functional/MAIL12MailboxSettingsTest.php` |

## Implementation structure

- `Routes.php`: all 39 specified routes and workflow transitions.
- `MailRepository.php`: mailbox ownership, domain-admin scope and audit persistence.
- `Auth.php`: HTTP-only persistent session authentication with token hashes.
- `Seeder.php`: deterministic actors, folders, messages, contacts, rules, domains and quarantine fixtures.
- `public/`: actor-facing browser console using HTML, CSS and vanilla JavaScript.

Errors use `{ "error": { "code", "message", "fields" } }`. Responses do not expose stack traces, paths, SQL errors, password hashes or session tokens. CSV exports contain only the documented business columns.\n\n## Two-layer HTTP security validation\n\nA PowerShell suite is included under `tests/exploit/`. It contains a `Core` comparison layer and a `P13_Specific` Mail Server Admin Console layer. Run `composer db:reset`, `composer start`, then execute `tests\\exploit\\run_all_tests.ps1` in another PowerShell.\n