pytest tests/security/test_exploit_vuln_001_upload_access_bac.py  -q
pytest tests/security/test_exploit_vuln_002_submission_attachment_logic.py  -q
pytest  tests/security/test_exploit_vuln_003_upload_storage_path_leak.py -q
pytest  tests/security/test_exploit_vuln_004_assignment_csrf.py -q