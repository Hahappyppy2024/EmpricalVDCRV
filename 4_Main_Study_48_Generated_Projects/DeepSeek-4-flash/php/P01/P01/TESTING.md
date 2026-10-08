# Testing P01

## Functional

```bash
php tests/Functional/run_functional_tests.php
```

## Security

Start the application:

```powershell
php bin/reset.php --drop
php -S 127.0.0.1:8080 -t public
```

Then:

```powershell
.\tests\exploit\run_all_tests.ps1
```

Read `tests/exploit/P01_SECURITY_ORACLE.md` before interpreting security FAIL results.
