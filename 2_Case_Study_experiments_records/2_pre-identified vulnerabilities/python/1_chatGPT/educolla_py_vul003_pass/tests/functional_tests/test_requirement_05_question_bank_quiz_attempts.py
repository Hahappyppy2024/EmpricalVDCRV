from helpers import (
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    json_of,
)


def test_requirement_05_question_bank_quiz_attempts(client, app_instance):
    conn = open_db()
    try:
        ok(login(client, "admin", "admin123"))

        ok(register(client, "req5_teacher", "pass123", "Req5 Teacher"))
        ok(register(client, "req5_student", "pass123", "Req5 Student"))

        teacher_id = get_user_id(conn, "req5_teacher")
        student_id = get_user_id(conn, "req5_student")
        assert teacher_id is not None
        assert student_id is not None

        ok(create_course(client, "Req5 Course", "Question quiz attempt course"))
        course_id = get_course_id(conn, "Req5 Course")
        assert course_id is not None

        ok(add_member(client, course_id, teacher_id, "teacher"))
        ok(add_member(client, course_id, student_id, "student"))

        # login as teacher
        ok(login(client, "req5_teacher", "pass123"))

        # create question
        r = client.post(
            f"/api/courses/{course_id}/questions",
            json={
                "qtype": "multiple-choice",
                "prompt": "What is 2 + 2?",
                "options_json": ["3", "4", "5"],
                "answer_json": "4",
            },
        )
        ok(r)
        question_id = json_of(r)["question_id"]

        # list questions
        r = client.get(f"/api/courses/{course_id}/questions")
        ok(r)
        assert any(q["question_id"] == question_id for q in json_of(r)["questions"])

        # get question
        r = client.get(f"/api/courses/{course_id}/questions/{question_id}")
        ok(r)
        assert json_of(r)["question"]["question_id"] == question_id

        # update question
        r = client.put(
            f"/api/courses/{course_id}/questions/{question_id}",
            json={
                "qtype": "multiple-choice",
                "prompt": "What is 3 + 3?",
                "options_json": ["5", "6", "7"],
                "answer_json": "6",
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT prompt FROM questions WHERE question_id=?",
            (question_id,),
        ).fetchone()
        assert row["prompt"] == "What is 3 + 3?"

        # create quiz
        r = client.post(
            f"/api/courses/{course_id}/quizzes",
            json={
                "title": "Req5 Quiz",
                "description": "Quiz for requirement 05",
                "open_at": "2026-01-01T00:00:00Z",
                "due_at": "2026-12-31T00:00:00Z",
            },
        )
        ok(r)
        quiz_id = json_of(r)["quiz_id"]

        # list quizzes
        r = client.get(f"/api/courses/{course_id}/quizzes")
        ok(r)
        assert any(q["quiz_id"] == quiz_id for q in json_of(r)["quizzes"])

        # get quiz
        r = client.get(f"/api/courses/{course_id}/quizzes/{quiz_id}")
        ok(r)
        assert json_of(r)["quiz"]["quiz_id"] == quiz_id

        # update quiz
        r = client.put(
            f"/api/courses/{course_id}/quizzes/{quiz_id}",
            json={
                "title": "Req5 Quiz Updated",
                "description": "Quiz updated",
                "open_at": "2026-01-01T00:00:00Z",
                "due_at": "2026-12-31T23:59:59Z",
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT title FROM quizzes WHERE quiz_id=?",
            (quiz_id,),
        ).fetchone()
        assert row["title"] == "Req5 Quiz Updated"

        # configure quiz questions
        r = client.post(
            f"/api/courses/{course_id}/quizzes/{quiz_id}/questions",
            json={"question_id": question_id, "points": 2, "position": 1},
        )
        ok(r)

        row = conn.execute(
            "SELECT quiz_id, question_id, points, position FROM quiz_questions WHERE quiz_id=? AND question_id=?",
            (quiz_id, question_id),
        ).fetchone()
        assert row is not None
        assert row["points"] == 2
        assert row["position"] == 1

        # login as student
        ok(login(client, "req5_student", "pass123"))

        # start attempt
        r = client.post(f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start", json={})
        ok(r)
        attempt_id = json_of(r)["attempt_id"]

        row = conn.execute(
            "SELECT attempt_id, submitted_at FROM quiz_attempts WHERE attempt_id=?",
            (attempt_id,),
        ).fetchone()
        assert row is not None
        assert row["submitted_at"] is None

        # answer question
        r = client.post(
            f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers",
            json={"question_id": question_id, "answer_json": "6"},
        )
        ok(r)

        row = conn.execute(
            "SELECT answer_id, answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?",
            (attempt_id, question_id),
        ).fetchone()
        assert row is not None

        # submit attempt
        r = client.post(
            f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit",
            json={},
        )
        ok(r)

        row = conn.execute(
            "SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?",
            (attempt_id,),
        ).fetchone()
        assert row is not None
        assert row["submitted_at"] is not None

        # view my attempts
        r = client.get("/api/my/quizzes/attempts")
        ok(r)
        assert "attempts" in json_of(r)
    finally:
        conn.close()