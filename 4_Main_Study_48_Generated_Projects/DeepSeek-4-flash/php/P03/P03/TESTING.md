# P03 Testing

## Functional tests

The existing PHP functional suite is unchanged. SHOP-01 through SHOP-13 are primary tests; SHOP-14 is auxiliary.

```powershell
php tests\Functional\run_functional_tests.php
```

## PowerShell security tests

Start P03:

```powershell
php bin\reset_db.php
php -S 127.0.0.1:8080 -t public public\index.php
```

Then:

```powershell
.\tests\exploit\run_all_tests.ps1
```

Optional target override:

```powershell
$env:P03_BASE_URL = "http://127.0.0.1:8000"
.\tests\exploit\run_all_tests.ps1
```

Inventory: **16 Core + 7 P03-specific = 23**.

Latest report:

```text
tests\exploit\reports\latest.json
```

Statuses: `PASS`, `FAIL`, `INCONCLUSIVE`, `N/A`, `ERROR`. A security `FAIL` is a finding, not a broken runner; only `ERROR` causes a nonzero runner exit.
