cd /d D:\000_phd_graudaiton\new_DRV\code\unkown\1_project-level\py\educolla_py
pytest -q tests\security_tests\test_vuln_001_default_admin_credentials.py ---fail
pytest -q tests\security_tests\test_vuln_002_user_list_access_control.py
pytest -q tests\security_tests\test_vuln_003_course_edit_delete_bac.py
pytest -q tests\security_tests\test_vuln_004_cross_course_membership_idor.py
pytest -q tests\security_tests\test_vuln_005_post_idor.py
pytest -q tests\security_tests\test_vuln_006_comment_idor.py
pytest -q tests\security_tests\test_vuln_007_question_answer_leak.py
pytest -q tests\security_tests\test_vuln_008_upload_path_traversal.py
pytest -q tests\security_tests\test_vuln_009_quiz_answer_tampering.py

 pytest -q tests\security_tests\
