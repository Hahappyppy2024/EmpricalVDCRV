cd /d D:\000_phd_graudaiton\new_DRV\code\1_project-level\py\educolla_py
.venv\Scripts\activate
pytest -q tests\functional_tests\functional.py

pytest -q tests/functional_tests

pytest -q tests/functional_tests/test_requirement_01_bootstrapping_auth_user_course.py


pytest -q tests/functional_tests/test_requirement_02_membership_role_enforcement.py

pytest -q tests/functional_tests/test_requirement_03_post_comment_search.py

pytest -q tests/functional_tests/test_requirement_04_assignment_submission_upload.py

pytest -q tests/functional_tests/test_requirement_05_question_bank_quiz_attempts.py