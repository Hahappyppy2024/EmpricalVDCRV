# P11 Security Tests Reworked from Reference CWE Types

The original functional tests are preserved. The security suite under `tests/exploit/` has been rewritten in PowerShell using the **29 unique CWE types from the supplied `tests(7).zip`** as the fixed detection dimensions, while each test is adapted to P11's real Hosting Control Panel implementation.

Run the application, then:

```powershell
.\tests\exploit\run_all_tests.ps1
```

See `tests/exploit/REFERENCE_CWE_TYPE_MAPPING.md` and `tests/exploit/SECURITY_ORACLE.md` for mapping and oracle details.
