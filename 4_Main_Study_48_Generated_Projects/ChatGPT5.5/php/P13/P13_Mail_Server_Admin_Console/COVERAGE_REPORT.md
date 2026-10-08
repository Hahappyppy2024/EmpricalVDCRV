# P13 Functional Test and Coverage Report

## Verdict

| Measure | Result |
|---|---:|
| Use-case test files | 12/12 passed |
| Assertions | 166 |
| Failures | 0 |
| Source files instrumented | 8/8 |
| Line coverage | 56/56 (100%) |
| Branch coverage | 207/219 (94.52%) |
| Path coverage | 104/182 (57.14%) |

Threshold: `94.52% >= 85%` — **PASS**.

## Test organization

| Use case | Test file | Assertions |
|---|---|---:|
| MAIL-01 Account access | `MAIL01AccountAccessTest.php` | 12 |
| MAIL-02 Mailbox overview | `MAIL02MailboxOverviewTest.php` | 14 |
| MAIL-03 Message compose | `MAIL03MessageComposeTest.php` | 13 |
| MAIL-04 Message reading | `MAIL04MessageReadingTest.php` | 15 |
| MAIL-05 Attachment handling | `MAIL05AttachmentHandlingTest.php` | 11 |
| MAIL-06 Contact management | `MAIL06ContactManagementTest.php` | 13 |
| MAIL-07 Filters and rules | `MAIL07FiltersRulesTest.php` | 14 |
| MAIL-08 Domain management | `MAIL08DomainManagementTest.php` | 17 |
| MAIL-09 Quarantine | `MAIL09QuarantineTest.php` | 16 |
| MAIL-10 Admin audit logs | `MAIL10AdminAuditLogsTest.php` | 13 |
| MAIL-11 Import/export | `MAIL11ImportExportTest.php` | 15 |
| MAIL-12 Mailbox settings | `MAIL12MailboxSettingsTest.php` | 13 |

The tests send PSR-7 requests through the complete Slim stack and use real PDO SQLite persistence. Assertions cover success plus authentication, role boundaries, mailbox/domain isolation, validation, duplicate operations, stale versions, invalid states and transactional CSV import behavior.

## Measurement

- PHP 8.3.6; Xdebug 3.2.0
- Xdebug flags: unused/dead code and branch/path checking
- Instrumentation scope: `src/*.php`
- Raw measurement: `var/coverage.json`
- Per-file summary: `var/coverage-summary.json`
- Test transcript: `var/coverage-test.log`

Branch coverage means 207 of 219 Xdebug branch nodes executed. It is not a claim that the application is 94.52% correct. Requirements conformance instead relies on the traceability table and the 12 use-case HTTP scenarios. Path coverage is diagnostic rather than the acceptance metric because combinations of individually covered decisions multiply possible paths.

## Reproduce

```bash
php bin/run-functional-tests.php
XDEBUG_MODE=coverage php bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

The summary command exits with status 1 if branch coverage is below 85%.
