# PHP Function Correctness Tests for EduCollab

This test suite targets the uploaded `educollab_php_v0` project and exercises the main functional flows through the JSON API.

## Covered flows

- auth bootstrap + seeded admin
- course creation
- membership add / update / remove
- posts + comments + search
- assignments + uploads + submissions
- question bank + quiz + student attempts

## Files

- `conftest.py` — fresh app copy + PHP built-in server fixture
- `test_function_correctness.py` — pytest function tests

.\.venv\Scripts\Activate.ps1
pytest -q test\functional_tests\test_function_correctness.py

pytest -q test\functional_tests\test_function_correctness.py -k quiz

## How to run

1. Make sure Python, pytest, requests, PHP CLI, and the SQLite PDO driver are installed.
2. Unzip this folder next to the PHP project, or edit `source_app_dir()` in `conftest.py`.
3. Run:

```bash
pytest -q
```

## Important environment note

The current container where this suite was prepared has `php` installed, but `pdo_sqlite` is missing, so full end-to-end execution could not be completed here. The tests themselves are syntactically valid (`py_compile` passed), and they are designed for a PHP environment where `pdo_sqlite` is enabled.

You can verify the required extension with:

```bash
php -m | grep -i sqlite
```
python -m venv .venv

.\.venv\Scripts\Activate.ps1

cd /d D:\000_phd_graudaiton\new_DRV\code\1_project-level\php\educollab_php_v0
.venv\Scripts\activate.bat
pytest -q test\functional_tests\test_requirement_01_bootstrapping_auth_user_course.py

pytest -q test\functional_tests\test_requirement_02_membership_role_enforcement.py

pytest -q test\functional_tests\test_requirement_03_post_comment_search.py

pytest -q test\functional_tests\test_requirement_04_assignment_submission_upload.py

pytest -q test\functional_tests\test_requirement_05_question_bank_quiz_attempts.py

