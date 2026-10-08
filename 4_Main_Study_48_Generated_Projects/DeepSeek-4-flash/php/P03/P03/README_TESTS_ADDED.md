# P03 Functional and Security Tests Added

This package keeps the P03 application and functional tests unchanged and restructures only the security harness into the unified PowerShell layout:

```text
tests/exploit/
├── config.ps1
├── core/
│   ├── bootstrap.ps1
│   ├── auth.ps1
│   ├── http.ps1
│   └── result.ps1
├── tests/Axx_*/
├── reports/
├── run_all_tests.ps1
└── test_manifest.json
```

Security inventory preserved from the uploaded P03 package:

- Core: **16**
- P03-specific: **7**
- Total: **23**

No application source file was modified.
