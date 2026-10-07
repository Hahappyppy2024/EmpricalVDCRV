
from helpers import (
    login, logout, register, create_course, add_member, create_question,
    create_quiz, add_quiz_question, start_attempt, answer_question, submit_attempt,
    get_user_id, get_row
)

def test_exploit_student_can_change_answers_after_submit_and_answer_non_quiz_question(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Quiz Tamper", "demo").json()["course"]

    assert register(session, base_url, "quiz_student", "pass123", "Quiz Student").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    student_id = get_user_id(db_path, "quiz_student")
    assert add_member(session, base_url, course["course_id"], student_id, "student").status_code == 200

    q1 = create_question(
        session, base_url, course["course_id"], "single_choice", "Q1", '["A","B"]', '{"correct":"A"}'
    ).json()["question"]
    q2 = create_question(
        session, base_url, course["course_id"], "single_choice", "Q2", '["X","Y"]', '{"correct":"Y"}'
    ).json()["question"]

    quiz = create_quiz(session, base_url, course["course_id"], "Quiz 1", "desc").json()["quiz"]
    assert add_quiz_question(session, base_url, course["course_id"], quiz["quiz_id"], q1["question_id"], 1, 1).status_code == 200

    logout(session, base_url)
    assert login(session, base_url, "quiz_student", "pass123").status_code == 200

    attempt = start_attempt(session, base_url, course["course_id"], quiz["quiz_id"]).json()["attempt"]
    attempt_id = attempt["attempt_id"]

    assert answer_question(session, base_url, course["course_id"], quiz["quiz_id"], attempt_id, q1["question_id"], '{"answer":"A"}').status_code == 200
    assert submit_attempt(session, base_url, course["course_id"], quiz["quiz_id"], attempt_id).status_code == 200

    after_submit = answer_question(
        session,
        base_url,
        course["course_id"],
        quiz["quiz_id"],
        attempt_id,
        q1["question_id"],
        '{"answer":"B"}',
    )
    assert after_submit.status_code == 200, after_submit.text

    foreign_q = answer_question(
        session,
        base_url,
        course["course_id"],
        quiz["quiz_id"],
        attempt_id,
        q2["question_id"],
        '{"answer":"Y"}',
    )
    assert foreign_q.status_code == 200, foreign_q.text

    row1 = get_row(
        db_path,
        "SELECT answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?",
        (attempt_id, q1["question_id"]),
    )
    row2 = get_row(
        db_path,
        "SELECT answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?",
        (attempt_id, q2["question_id"]),
    )
    attempt_row = get_row(
        db_path,
        "SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?",
        (attempt_id,),
    )

    assert attempt_row["submitted_at"] is not None
    assert row1["answer_json"] == '{"answer":"B"}'
    assert row2["answer_json"] == '{"answer":"Y"}'
