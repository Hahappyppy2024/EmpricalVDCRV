# P02 Testing

This test suite was written against the actual `P02` Conference Review System implementation.

## Functional tests

The functional suite maps one test to every Version-A use case (`CONF-01` through `CONF-12`) plus a cross-use-case edge-condition test.

Run:

```bash
php tests/Functional/run_functional_tests.php
```

The runner resets and reseeds the SQLite database before every test file. Results are written to `tests/Functional/functional_results.json`.

## Security tests

Start P02 first:

```bash
php bin/reset_db.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Then, from PowerShell:

```powershell
.\tests\exploit\run_all_tests.ps1
```

If P02 runs on another URL:

```powershell
$env:P02_BASE_URL = "http://127.0.0.1:8081"
.\tests\exploit\run_all_tests.ps1
```

Security results use these semantics:

- `PASS`: the secure behavior/oracle held; the attempted attack was blocked.
- `FAIL`: the attack or unsafe behavior was observed; this is a vulnerability signal, not a broken test.
- `ERROR`: the test could not establish a valid precondition or encountered an execution problem.
- `N/A`: the concrete behavioral probe cannot run in the current environment.

Do **not** rewrite a security test merely to make a vulnerable version green.
