# PHP R5 tests (question bank + quiz + student attempts)

This package targets only the R5-added API surface:
- question bank APIs
- quiz APIs
- quiz question configuration APIs
- student quiz attempt APIs
- `/api/my/quizzes/attempts`

Older requirements (R1-R4) are used only as setup prerequisites.

## Files
- `tests/functional_tests/test_api_functional_r5.py`
- `tests/security_tests/exploit_tests/test_api_exploit_r5.py`
- `tests/conftest.py`
- `tests/helpers.py`

## Run
```bash
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests

pytest -q tests/test_api_functional_r5.py
pytest -q tests/test_api_exploit_r5.py
```

## Exploit coverage
1. Student can retrieve `answer_json` (correct answers) through quiz detail API.
2. Student can still start and answer an attempt even when the quiz is already past due.
