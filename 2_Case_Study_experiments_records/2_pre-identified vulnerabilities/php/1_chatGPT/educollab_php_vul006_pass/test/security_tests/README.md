cd /d D:\000_phd_graudaiton\new_DRV\code\known\php\educollab_php_v0
pytest test\security_tests\vul001_session_fixation.py -v
pytest test\security_tests\vul002_cross_course_membership_idor.py -v
pytest test\security_tests\vul003_unauthorized_post_edit.py -v
pytest test\security_tests\vul004_unauthorized_comment_edit.py -v
pytest test\security_tests\vul005_question_answer_key_exposure.py -v
pytest test\security_tests\vul006_cross_course_quiz_attempt_idor.py -v
pytest test\security_tests\vul007_foreign_question_attachment.py -v

.\.venv\Scripts\Activate.ps1
