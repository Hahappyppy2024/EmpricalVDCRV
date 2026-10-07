cd /d D:\\000\_phd\_graudaiton\\new\_DRV\\code\\unkown\\1\_project-level\\php\\educollab\_php\_v0
pytest -q test\\security\_tests\\test\_vuln\_001\_default\_admin\_credentials.py

cd /d D:\\000\_phd\_graudaiton\\new\_DRV\\code\\unkown\\1\_project-level\\php\\educollab\_php\_v0


python -m venv venv

.venv\Scripts\activate

pytest test\security_tests\

pytest -q test/security_tests/test_vuln_001_default_admin_credentials.py

pytest -q test/security_tests/test_vuln_002_session_fixation.py

pytest -q test/security_tests/test_vuln_003_csrf.py

pytest -q test/security_tests/test_vuln_004_cross_course_membership_idor.py

pytest -q test/security_tests/test_vuln_005_post_idor.py

pytest -q test/security_tests/test_vuln_006_comment_idor.py

pytest -q test/security_tests/test_vuln_007_question_bank_answer_leak.py

pytest -q test/security_tests/test_vuln_008_quiz_answer_tampering.py

pytest -q test/security_tests/test_vuln_008_upload_path_traversal.py