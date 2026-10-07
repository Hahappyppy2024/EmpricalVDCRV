from helpers import ApiClient


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_staff_can_attach_foreign_course_question_to_local_quiz(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher_a = ApiClient.new(app_instance["base_url"])
    teacher_a_user = teacher_a.register("teacher_course_a", "pass123", "Teacher Course A")
    teacher_b = ApiClient.new(app_instance["base_url"])
    teacher_b_user = teacher_b.register("teacher_course_b", "pass123", "Teacher Course B")

    course_a = build_course_with_members(admin, "Course A", [(teacher_a_user["user_id"], "teacher")])
    course_b = build_course_with_members(admin, "Course B", [(teacher_b_user["user_id"], "teacher")])

    teacher_b.login("teacher_course_b", "pass123")
    foreign_question = teacher_b.create_question(course_b["course_id"], "foreign prompt", '{"value":7}')

    teacher_a.login("teacher_course_a", "pass123")
    local_quiz = teacher_a.create_quiz(course_a["course_id"], "Local Quiz")

    attach = teacher_a.add_quiz_question(
        course_a["course_id"],
        local_quiz["quiz_id"],
        foreign_question["question_id"],
        points=3,
        position=1,
    )
    assert attach.status_code == 200, attach.text

    body = attach.json()
    assert body is not None
    if isinstance(body, dict):
        assert "error" not in body
        assert "message" not in body or "not allowed" not in str(body["message"]).lower()

    quiz_view = teacher_a.get_quiz(course_a["course_id"], local_quiz["quiz_id"])

    if isinstance(quiz_view, dict) and "quiz_questions" in quiz_view:
        question_ids = []
        for row in quiz_view["quiz_questions"]:
            if isinstance(row, dict) and "question_id" in row:
                question_ids.append(int(row["question_id"]))
        assert int(foreign_question["question_id"]) in question_ids