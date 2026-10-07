from database import get_db_connection
from conftest import add_membership


def test_quiz_api_flow_without_template_render(app, client, auth, admin_logged_in, course_with_admin, student_user):
    course_id = course_with_admin

    # Create question via API
    rv = client.post(
        f"/api/courses/{course_id}/questions",
        json={
            "qtype": "multiple-choice",
            "prompt": "What is 2+2?",
            "options_json": '["3","4","5"]',
            "answer_json": '"4"',
        },
    )
    assert rv.status_code == 201
    qid = rv.get_json()["question_id"]

    # Create quiz via API
    rv = client.post(
        f"/api/courses/{course_id}/quizzes",
        json={"title": "Math Quiz", "description": "Easy"},
    )
    assert rv.status_code == 201
    quiz_id = rv.get_json()["quiz_id"]

    # Attach question to quiz
    rv = client.post(
        f"/api/courses/{course_id}/quizzes/{quiz_id}/questions",
        json={"question_id": qid, "points": 1, "position": 1},
    )
    assert rv.status_code == 200

    # Student membership + start attempt via API (needs student role)
    add_membership(app, course_id, student_user, "student")
    auth.logout()
    auth.api_login("student", "pass")

    rv = client.post(f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start")
    assert rv.status_code == 201
    attempt_id = rv.get_json()["attempt_id"]

    rv = client.post(
        f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers",
        json={"question_id": qid, "answer_json": '"4"'},
    )
    assert rv.status_code == 200

    rv = client.post(
        f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit",
    )
    assert rv.status_code == 200

    # Verify attempt is marked submitted in DB
    with app.app_context():
        conn = get_db_connection()
        row = conn.execute("SELECT submitted_at FROM quiz_attempt WHERE attempt_id=?", (attempt_id,)).fetchone()
        conn.close()
        assert row is not None
        assert row["submitted_at"] is not None
