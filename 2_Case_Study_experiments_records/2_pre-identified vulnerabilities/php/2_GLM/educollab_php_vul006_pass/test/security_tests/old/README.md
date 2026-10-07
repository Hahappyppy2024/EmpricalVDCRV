# EduCollab PHP security tests: exploit vs regression

This package separates the tests into two categories.

## 1) Exploit tests
File: `test_exploit_confirmed_vulns.py`

These are **attack-success tests**.
They are expected to **pass when the application is still vulnerable**.
They demonstrate that the exploit path works.

Examples:
- login with hardcoded admin credentials succeeds
- session ID is not rotated after login
- a teacher in Course A can modify a membership in Course B
- a student can read `answer_json`

Run:
```bash
pytest -q test\security_tests\test_exploit_confirmed_vulns.py
```

## 2) Regression tests
File: `test_regression_secure_behavior.py`

These are **security-fix tests**.
They are expected to **pass only after the vulnerability is fixed**.
They assert the secure behavior instead of the vulnerable behavior.

Examples:
- hardcoded default admin login is rejected
- login rotates the session ID
- cross-course object access is denied
- non-owner edits are denied
- answer keys are hidden from students

Run:
```bash
pytest -q security_tests/test_regression_secure_behavior.py
```

## Important note
Your earlier package was much closer to an **exploit / vulnerability-demonstration test suite**, not a regression suite.

## Prerequisites
- PHP 8+
- `pdo_sqlite` enabled
- Python 3.10+
- `pip install -r security_tests/requirements.txt`
