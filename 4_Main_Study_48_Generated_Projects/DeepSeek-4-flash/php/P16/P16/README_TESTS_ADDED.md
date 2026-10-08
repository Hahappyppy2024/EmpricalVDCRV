# Tests added for P16

P16 keeps the original **12 primary functional tests + 1 auxiliary cross-use-case test** unchanged.

The security suite has been replaced with a PowerShell suite aligned to the **CWE types in `tests(9).zip`**:

- 30 unique reference CWE types
- 30 P16 primary CWE security tests
- 1 seeded-login precondition
- P16-specific routes, seed accounts, cookie, fields, and workflow states

## Functional

```powershell
php tests/Functional/run_functional_tests.php
```

## Security

Start/reset P16 as documented by the application, then run:

```powershell
.\tests\exploit\run_all_tests.ps1
```

Optional target override:

```powershell
$env:P16_BASE_URL='http://127.0.0.1:8080'
.\tests\exploit\run_all_tests.ps1
```

Security labels are defined in `tests/exploit/SECURITY_ORACLE.md`.
A security `FAIL` is a finding, not a harness failure; only `ERROR` makes the runner exit nonzero.
