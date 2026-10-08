# P07 Testing

## Functional
Functional tests are unchanged from the uploaded project.

## Security
The security suite is PowerShell and covers 32 unique CWE types selected from the reference `tests(6)` suite.

```powershell
.\tests\exploit\run_all_tests.ps1
```

Only `ERROR` indicates a test/setup execution failure. `FAIL` is a valid vulnerability finding.
