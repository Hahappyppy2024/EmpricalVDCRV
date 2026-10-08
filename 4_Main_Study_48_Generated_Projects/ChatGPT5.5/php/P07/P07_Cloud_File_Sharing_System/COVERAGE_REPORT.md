# P07 Functional Test and Coverage Report

## Verdict

The project satisfies the requested branch-coverage threshold.

| Measure | Result |
|---|---:|
| Use-case test files | 12/12 passed |
| Assertions | 183 |
| Failures | 0 |
| Source files instrumented | 11/11 |
| Line coverage | 693/729 (95.06%) |
| Branch coverage | 363/404 (89.85%) |
| Path coverage | 143/4496 (3.18%) |

Threshold check: `89.85% >= 85%` — **PASS**.

## Test organization

Every specified use case has one primary functional test file:

| Use case | Test file | Assertions |
|---|---|---:|
| FILE-01 Account access | `FILE01AccountAccessTest.php` | 20 |
| FILE-02 File upload | `FILE02FileUploadTest.php` | 12 |
| FILE-03 Folder management | `FILE03FolderManagementTest.php` | 17 |
| FILE-04 Download and preview | `FILE04DownloadPreviewTest.php` | 13 |
| FILE-05 Sharing links | `FILE05SharingLinksTest.php` | 14 |
| FILE-06 Team spaces | `FILE06TeamSpacesTest.php` | 16 |
| FILE-07 Search | `FILE07SearchTest.php` | 14 |
| FILE-08 Version history | `FILE08VersionHistoryTest.php` | 13 |
| FILE-09 Trash and restore | `FILE09TrashRestoreTest.php` | 16 |
| FILE-10 Storage quota | `FILE10StorageQuotaTest.php` | 15 |
| FILE-11 Audit log and exports | `FILE11AuditLogExportsTest.php` | 14 |
| FILE-12 Admin console | `FILE12AdminConsoleTest.php` | 19 |

The tests execute requests through the Slim application, use real PDO SQLite persistence, create real temporary upload files, and verify status codes and response data. Each test file receives a fresh seeded database and upload directory.

## Measurement environment

- PHP 8.3.6
- Xdebug 3.2.0
- Xdebug flags: unused lines, dead code, and branch/path checking
- Measurement scope: `src/*.php` only

Raw instrumentation is stored in `var/coverage.json`; the structured summary and per-file counts are stored in `var/coverage-summary.json`; complete test output is stored in `var/coverage-test.log`.

## Interpretation

Branch coverage means that tests executed 363 of the 404 branch nodes reported by Xdebug. It does not mean the software is 89.85% correct or that 89.85% of requirements are implemented. Requirement implementation is instead supported by the use-case traceability table in `README.md` and the twelve passing HTTP-level tests.

Path coverage is deliberately not used as the acceptance gate. The seed routine alone produces thousands of enumerated control-flow paths because loop and condition combinations grow combinatorially; this makes global path percentage structurally sensitive and unsuitable as the primary completeness criterion.

## Reproduction

```bash
php bin/run-functional-tests.php
XDEBUG_MODE=coverage php bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

`summarize-coverage.php` exits with a non-zero status when branch coverage is below 85%, so the threshold is executable rather than merely documented.
