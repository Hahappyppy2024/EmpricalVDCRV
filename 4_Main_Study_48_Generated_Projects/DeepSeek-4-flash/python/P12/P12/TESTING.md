# P12 Testing

## Functional

```powershell
python tests\Functional\run_functional_tests.py
```

There are 12 primary use-case tests plus one auxiliary cross-use-case test.

## Security

The security suite now follows the CWE-type set in
`tests(20260820-121007).zip`: **28 unique CWE types**, reimplemented against the
actual P12 `/api/data/...` application.

```powershell
.\tests\exploit\run_all_tests.ps1
```

Results:

- `PASS` — exact secure oracle satisfied
- `FAIL` — candidate security finding
- `INCONCLUSIVE` — automation cannot establish final verdict
- `N/A` — no matching executable P12 surface
- `ERROR` — test/environment failure

Only `ERROR` makes the PowerShell runner exit nonzero.
