REM ===== Security tests: one cmd per file =====
python -m pytest -q tests/security_tests/tests/test_R01_access_control__test_idor_submission_update_other_user.py

python -m pytest -q tests/security_tests/tests/test_R04_crypto__test_crypto_session_cookie_flags.py
python -m pytest -q tests/security_tests/tests/test_R04_crypto__test_crypto_session_fixation.py
python -m pytest -q tests/security_tests/tests/test_R04_crypto__test_INV_CRYPTO_invite_link.py

python -m pytest -q tests/security_tests/tests/test_R05_injection__test_sqli_search_posts_keyword.py
python -m pytest -q tests/security_tests/tests/test_R05_injection__test_xss_reflected_search_keyword.py
python -m pytest -q tests/security_tests/tests/test_R05_injection__test_xss_stored_comment.py
python -m pytest -q tests/security_tests/tests/test_R05_injection__test_xss_stored_post.py

python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_download_nosniff_header.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FL_FR1_zip_slip_unzip.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FL_FR3_export_zip_requires_staff.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FL_FR4_download_zip_should_be_scoped.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FL_FR5_export_grades_csv_no_formula_cells.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FR_CSV1_export_members_csv.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FR_CSV2_import_members_csv.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FR_CSV3_export_assignment_grades_csv.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FR_MAT1_upload_zip_extract.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_FR_MAT3_download_materials_zip.py
python -m pytest -q tests/security_tests/tests/test_R08_integrity__test_upload_filename_path_traversal.py

python -m pytest -q tests/security_tests/tests/test_R09_auditlog__test_A09_audit_log_missing_critical_events.py

python -m pytest -q tests/security_tests/tests/test_R10_exception__test_exception_404_no_leak.py
python -m pytest -q tests/security_tests/tests/test_R10_exception__test_exception_malformed_json.py

python -m pytest -q tests/security_tests/tests/test_UNK__test_FL_FR2_list_files_requires_membership.py
python -m pytest -q tests/security_tests/tests/test_UNK__test_FL_FR6_import_grades_requires_staff.py
python -m pytest -q tests/security_tests/tests/test_UNK__test_FR_MAT2_list_materials_files.py

# EduCollab PHP v0 — Security Tests (pytest)

These tests are **validation-oriented** checks for common web vuln surfaces (XSS, SQLi, upload/path issues, basic IDOR) against the EduCollab **PHP v0** app.

## Prereqs

```bash
pip install -r requirements.txt
```

## Start the PHP app

From the EduCollab PHP v0 project root:

```bash
php -S localhost:8000 -t public
```

## Run tests

```bash
pytest -q
```

## Configure base URL

Default target is `http://localhost:8000`.

```bash
BASE_URL=http://localhost:8000 pytest -q
```
