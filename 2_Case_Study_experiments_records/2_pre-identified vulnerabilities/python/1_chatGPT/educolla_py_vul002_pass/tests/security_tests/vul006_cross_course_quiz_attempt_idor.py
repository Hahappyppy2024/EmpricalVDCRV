from helpers import ApiClient


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_student_in_course_a_can_start_quiz_from_course_b_by_swapping_route_course_id(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher = ApiClient.new(app_instance["base_url"])
    teacher_user = teacher.register("quiz_teacher", "pass123", "Quiz Teacher")
    student_a = ApiClient.new(app_instance["base_url"])
    student_a_user = student_a.register("student_a", "pass123", "Student A")

    course_a = build_course_with_members(admin, "Course A", [(student_a_user["user_id"], "student")])
    course_b = build_course_with_members(admin, "Course B", [(teacher_user["user_id"], "teacher")])

    teacher.login("quiz_teacher", "pass123")
    q_b = teacher.create_question(course_b["course_id"], "secret prompt", '{"value":"secret"}')
    quiz_b = teacher.create_quiz(course_b["course_id"], "Quiz B")
    add_resp = teacher.add_quiz_question(
        course_b["course_id"],
        quiz_b["quiz_id"],
        q_b["question_id"],
        points=5,
        position=1,
    )
    assert add_resp.status_code == 200, add_resp.text

    student_a.login("student_a", "pass123")
    start = student_a.start_attempt(course_a["course_id"], quiz_b["quiz_id"])
    assert start.status_code == 200, start.text

    body = start.json()
    assert body is not None

    # exploit 核心：不属于 quiz 所在课程的学生，仍然成功启动 attempt
    # 这里不强绑定响应 JSON 的具体字段结构，只检查“启动成功且返回了某种 attempt 结果”
    if isinstance(body, dict):
        assert "error" not in body
        assert "message" not in body or "not allowed" not in str(body["message"]).lower()