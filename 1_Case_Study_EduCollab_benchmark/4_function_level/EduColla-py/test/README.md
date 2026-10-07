python -m pytest -q security_tests\web --ignore=security_tests\web
python -m pytest -q security_tests\api --ignore=security_tests\api


python app.py

# =========================
# Security tests (API)  8
# =========================
python -m pytest -q test/security_tests/api/R01_access_control__test_access_control_api.py

[//]: # controllers (@role_required&#40;course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant']&#41;)

# ==================================================================================================

python -m pytest -q test/security_tests/api/R04_crypto__test_invite_crypto_api.py

[//]: # 28 79 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\models\invite.py)



# ==================================================================================================
python -m pytest -q test/security_tests/api/R05_injection__test_sqli_api.py

[//]: #61  def search(course_id: int, keyword: str): (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\models\post.py)


# ==================================================================================================
python -m pytest -q test/security_tests/api/R05_injection__test_xss_api.py

[//]: # 69 R05_injection__test_xss_api.py::test_api_returns_json_for_post_detail (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\post.py)
[//]: # 6 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\templates\posts\detail.html)
[//]: # 171 R05_injection__test_xss_api.py::test_api_returns_json_for_post_detail (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\post.py)

# ==================================================================================================
python -m pytest -q test/security_tests/api/R07_auth__test_session_api.py

[//]:10  # (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\decorators.py)

[//]: # 56 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\auth.py)

# ==================================================================================================
python -m pytest -q test/security_tests/api/R08_integrity__test_upload_api.py

[//]: # R08_integrity__test_upload_api.py::test_api_upload_path_traversal_sanitized - 14 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\models\upload.py)

[//]: #  66 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\upload.py)



# ==================================================================================================
python -m pytest -q test/security_tests/api/R10_exception__test_exceptional_conditions_api.py 

[//]: #135  (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\course.py)

[//]: #57  66 (D:\000_phd_graudaiton\04_web_attack\Empricial_Study_Web_Attack_VM\code_for_vm\educollab_python\controllers\upload.py)
# =========================
# Security tests (WEB) 
# =========================
python -m pytest -q test/security_tests/web/R01_access_control__test_access_control_web.py
python -m pytest -q test/security_tests/web/R02_misconfig__test_misconfig_web.py
python -m pytest -q test/security_tests/web/R05_injection__test_sqli_web.py
python -m pytest -q test/security_tests/web/R05_injection__test_xss_web.py
python -m pytest -q test/security_tests/web/R07_auth__test_session_web.py
python -m pytest -q test/security_tests/web/R08_integrity__test_upload_web.py
python -m pytest -q test/security_tests/web/R09_auditlog__test_A09_audit_missing_critical_events.py
python -m pytest -q test/security_tests/web/R10_exception__test_exceptional_conditions_web.py
python -m pytest -q test/security_tests/web/R10_exception__test_global_security.py



python -m pytest -q test/function_tests
# =========================
# Function tests (single file) 15
# =========================
python -m pytest -q test/function_tests/FUNC__test_assignment.py 1
python -m pytest -q test/function_tests/FUNC__test_course.py 2
python -m pytest -q test/function_tests/FUNC__test_invite_link.py 1

python -m pytest -q test/function_tests/FUNC__test_post.py 3
python -m pytest -q test/function_tests/FUNC__test_quiz.py 1
python -m pytest -q test/function_tests/FUNC__test_resource.py 2
 python -m pytest -q test/function_tests/R09_auditlog__test_a09_audit_log.py 1
python -m pytest -q test/function_tests/R08_integrity__test_upload.py   1
python -m pytest -q test/function_tests/R07_auth__test_auth.py   2
# =========================
# Extra tests shown in folder
# =========================
python -m pytest -q test/function_tests/R07_auth__test_auth.py
python -m pytest -q test/function_tests/R08_integrity__test_upload.py
python -m pytest -q test/function_tests/R09_auditlog__test_a09_audit_log.py