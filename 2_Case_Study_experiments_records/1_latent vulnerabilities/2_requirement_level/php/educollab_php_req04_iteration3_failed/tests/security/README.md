pytest -q tests/security/test_vuln_001_insecure_file_upload.py
pytest -q tests/security/test_vuln_002_submission_attachment_idor.py

pytest -q tests/test_api_functional_r4.py