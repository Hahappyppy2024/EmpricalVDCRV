# Tests added for P13

The `tests/` directory contains:

- 12 primary functional tests mapped 1:1 to MAIL-01 ... MAIL-12.
- 1 auxiliary cross-use-case integration/edge test.
- 30 primary PowerShell security tests, one for each unique CWE type in the `tests(8)` reference suite.
- The security probes are adapted to the current P13 routes, seed accounts, session cookie, role names, and persisted data.

Run functional tests:

`php tests/Functional/run_functional_tests.php`

Run security tests against a running server:

`.\tests\exploit\run_all_tests.ps1`
