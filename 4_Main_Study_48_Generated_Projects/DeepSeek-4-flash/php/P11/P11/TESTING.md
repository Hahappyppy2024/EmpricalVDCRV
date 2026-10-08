# P11 Testing

## Functional

```powershell
php tests/Functional/run_functional_tests.php
```

## Security — reference CWE type suite

```powershell
.\tests\exploit\run_all_tests.ps1
```

Override the target URL when needed:

```powershell
$env:P11_BASE_URL = "http://127.0.0.1:8080"
.\tests\exploit\run_all_tests.ps1
```

The security runner executes one seeded-login precondition and 29 primary CWE-type tests. `PASS`, `FAIL`, `INCONCLUSIVE`, `N/A`, and `ERROR` follow `tests/exploit/SECURITY_ORACLE.md`. Only `ERROR` makes the runner exit non-zero.
