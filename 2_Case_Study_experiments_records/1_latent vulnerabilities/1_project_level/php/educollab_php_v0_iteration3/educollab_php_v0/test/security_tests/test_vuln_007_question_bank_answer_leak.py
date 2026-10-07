
from helpers import login, logout, register, create_course, add_member, create_question, list_questions, get_question, get_user_id

def test_exploit_student_can_read_question_answer_json(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Quiz Leak", "demo").json()["course"]

    assert register(session, base_url, "student_q", "pass123", "StudentQ").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    student_id = get_user_id(db_path, "student_q")
    assert add_member(session, base_url, course["course_id"], student_id, "student").status_code == 200

    question = create_question(
        session,
        base_url,
        course["course_id"],
        "single_choice",
        "2+2=?",
        '["3","4","5"]',
        '{"correct":"4"}',
    ).json()["question"]

    logout(session, base_url)
    assert login(session, base_url, "student_q", "pass123").status_code == 200

    listed = list_questions(session, base_url, course["course_id"])
    assert listed.status_code == 200
    questions = listed.json()["questions"]
    leaked = [q for q in questions if q["question_id"] == question["question_id"]][0]
    assert leaked["answer_json"] == '{"correct":"4"}'

    detail = get_question(session, base_url, course["course_id"], question["question_id"])
    assert detail.status_code == 200
    assert detail.json()["question"]["answer_json"] == '{"correct":"4"}'
