# PHP R1 functional tests + exploit tests

## Structure

- `tests/test_api_functional_r1.py`
- `tests/exploit_tests/test_api_exploit_r1.py`
- `tests/conftest.py`

## Install

```bash
pip install pytest requests
```

## Run all
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests

```bash
pytest -q tests
```

## Run only functional tests

```bash
python -m pytest tests\test_api_functional_r1.py -vv -s
```

## Run only exploit tests

```bash
python -m pytest tests\test_api_exploit_r1.py -vv -s
```

## What the exploit tests demonstrate

These exploit tests are **vulnerability-confirmation tests** for the current R1 codebase:

1. Any authenticated user can update a course created by another user.
2. Any authenticated user can delete a course created by another user.

That behavior happens because `/api/courses/{id}` update/delete only check `require_login(...)`, but do not enforce ownership or admin-only authorization.
