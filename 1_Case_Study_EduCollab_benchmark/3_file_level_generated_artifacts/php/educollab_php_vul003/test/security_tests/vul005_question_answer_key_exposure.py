import json

from helpers import ApiClient


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def test_exploit_student_can_read_question_answer_key_via_api(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher = ApiClient.new(app_instance["base_url"])
    teacher_user = teacher.register("question_teacher", "pass123", "Question Teacher")
    student = ApiClient.new(app_instance["base_url"])
    student_user = student.register("question_student", "pass123", "Question Student")

    course = build_course_with_members(
        admin,
        "Question Course",
        [(teacher_user["user_id"], "teacher"), (student_user["user_id"], "student")],
    )

    teacher.login("question_teacher", "pass123")
    question = teacher.create_question(course["course_id"], "2 + 2 = ?", '{"value":4}')

    student.login("question_student", "pass123")
    leaked = student.get_question(course["course_id"], question["question_id"])

    leaked_answer = leaked["answer_json"]
    if isinstance(leaked_answer, str):
        try:
            leaked_answer = json.loads(leaked_answer)
        except Exception:
            pass

    if isinstance(leaked_answer, str):
        try:
            leaked_answer = json.loads(leaked_answer)
        except Exception:
            pass

    assert leaked_answer == {"value": 4}