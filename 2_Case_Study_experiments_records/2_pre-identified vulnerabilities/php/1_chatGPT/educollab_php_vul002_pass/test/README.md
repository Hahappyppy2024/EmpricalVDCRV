- `conftest.py` — fresh app copy + PHP built-in server fixture
- `test_function_correctness.py` — pytest function tests


pytest -q test\functional_tests\test_function_correctness.py

pytest -q test\functional_tests\test_function_correctness.py -k quiz






Run:
```bash
pytest -q test\security_tests\test_exploit_confirmed_vulns.py
```