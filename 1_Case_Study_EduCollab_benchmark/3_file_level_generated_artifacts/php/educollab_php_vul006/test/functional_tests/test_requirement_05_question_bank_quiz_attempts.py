import requests

from helpers import (
    app_instance,
    client,
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    api_url,
)


def test_requirement_05_question_bank_quiz_attempts(client, app_instance):
    _, base_url = client
    conn = open_db(app_instance["db_path"])

    admin = requests.Session()
    teacher = requests.Session()
    student = requests.Session()

    for s in (admin, teacher, student):
        s.headers.update({"Accept": "application/json"})

    try:
        # login / register
        ok(login(admin, base_url, "admin", "admin123"))
        ok(register(teacher, base_url, "req5_teacher", "pass123", "Req5 Teacher"))
        ok(register(student, base_url, "req5_student", "pass123", "Req5 Student"))

        teacher_id = get_user_id(conn, "req5_teacher")
        student_id = get_user_id(conn, "req5_student")
        assert teacher_id is not None
        assert student_id is not None

        # create course and add members
        ok(create_course(admin, base_url, "Req5 Course", "Question quiz attempt course"))
        course_id = get_course_id(conn, "Req5 Course")
        assert course_id is not None

        ok(add_member(admin, base_url, course_id, teacher_id, "teacher"))
        ok(add_member(admin, base_url, course_id, student_id, "student"))

        # --------------------
        # Question bank flow
        # --------------------
        # create question
        r = teacher.post(
            api_url(base_url, f"/api/courses/{course_id}/questions"),
            json={
                "qtype": "multiple-choice",
                "prompt": "What is 2 + 2?",
                "options_json": '["3","4","5"]',
                "answer_json": '"4"',
            },
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT question_id FROM questions WHERE course_id=? ORDER BY question_id DESC",
            (course_id,),
        ).fetchone()
        assert row is not None
        question_id = row["question_id"]

        # list questions
        ok(
            teacher.get(
                api_url(base_url, f"/api/courses/{course_id}/questions"),
                timeout=5,
                allow_redirects=False,
            )
        )

        # get question
        ok(
            teacher.get(
                api_url(base_url, f"/api/courses/{course_id}/questions/{question_id}"),
                timeout=5,
                allow_redirects=False,
            )
        )

        # update question
        r = teacher.put(
            api_url(base_url, f"/api/courses/{course_id}/questions/{question_id}"),
            json={
                "qtype": "multiple-choice",
                "prompt": "What is 3 + 3?",
                "options_json": '["5","6","7"]',
                "answer_json": '"6"',
            },
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT prompt, answer_json FROM questions WHERE question_id=?",
            (question_id,),
        ).fetchone()
        assert row["prompt"] == "What is 3 + 3?"
        assert row["answer_json"] == '"6"'

        # --------------------
        # Quiz management flow
        # --------------------
        # create quiz
        r = teacher.post(
            api_url(base_url, f"/api/courses/{course_id}/quizzes"),
            json={
                "title": "Req5 Quiz",
                "description": "Quiz for requirement 05",
                "open_at": "2026-01-01T00:00:00Z",
                "due_at": "2026-12-31T00:00:00Z",
            },
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT quiz_id FROM quizzes WHERE course_id=? ORDER BY quiz_id DESC",
            (course_id,),
        ).fetchone()
        assert row is not None
        quiz_id = row["quiz_id"]

        # list quizzes
        ok(
            teacher.get(
                api_url(base_url, f"/api/courses/{course_id}/quizzes"),
                timeout=5,
                allow_redirects=False,
            )
        )

        # get quiz
        ok(
            teacher.get(
                api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}"),
                timeout=5,
                allow_redirects=False,
            )
        )

        # update quiz
        r = teacher.put(
            api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}"),
            json={
                "title": "Req5 Quiz Updated",
                "description": "Quiz updated",
                "open_at": "2026-01-01T00:00:00Z",
                "due_at": "2026-12-31T23:59:59Z",
            },
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT title, description FROM quizzes WHERE quiz_id=?",
            (quiz_id,),
        ).fetchone()
        assert row["title"] == "Req5 Quiz Updated"
        assert row["description"] == "Quiz updated"

        # configure quiz question
        r = teacher.post(
            api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/questions"),
            json={"question_id": question_id, "points": 2, "position": 1},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT quiz_id, question_id, points, position FROM quiz_questions WHERE quiz_id=? AND question_id=?",
            (quiz_id, question_id),
        ).fetchone()
        assert row is not None
        assert row["points"] == 2
        assert row["position"] == 1

        # --------------------
        # Student attempt flow
        # --------------------
        # start attempt
        r = student.post(
            api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start"),
            json={},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT attempt_id, submitted_at FROM quiz_attempts WHERE quiz_id=? AND student_id=? ORDER BY attempt_id DESC",
            (quiz_id, student_id),
        ).fetchone()
        assert row is not None
        attempt_id = row["attempt_id"]
        assert row["submitted_at"] is None

        # answer question
        r = student.post(
            api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers"),
            json={"question_id": question_id, "answer_json": '"6"'},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT answer_id, answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?",
            (attempt_id, question_id),
        ).fetchone()
        assert row is not None
        assert row["answer_json"] == '"6"'

        # submit attempt
        r = student.post(
            api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit"),
            json={},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?",
            (attempt_id,),
        ).fetchone()
        assert row is not None
        assert row["submitted_at"] is not None

        # view my attempts
        ok(
            student.get(
                api_url(base_url, "/api/my/quizzes/attempts"),
                timeout=5,
                allow_redirects=False,
            )
        )

        # This requirement-level functional test focuses on validating the
        # main question-bank, quiz-management, and student-attempt workflow.
        # Cleanup via delete endpoints is intentionally omitted here to avoid
        # coupling the test outcome to optional teardown behavior.
    finally:
        conn.close()