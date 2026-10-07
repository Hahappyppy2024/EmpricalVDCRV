[//]: # (pytest -q tests/security/test_vuln_001_hardcoded_admin_credentials.py)
pytest -q tests/security/test_vuln_002_session_fixation.py
pytest -q tests/security/test_vuln_003_user_directory_exposure.py
pytest -q tests/security/test_vuln_004_course_idor.py
pytest -q tests/security/test_vuln_005_csrf_missing.py


python -m pytest tests\test_api_functional_r1.py -vv -s