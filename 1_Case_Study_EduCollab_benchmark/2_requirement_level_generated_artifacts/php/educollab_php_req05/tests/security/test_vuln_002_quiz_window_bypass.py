import json
import requests


def api_url(base_url: str, path: str) -> str:
    return base_url.rstrip("/") + path


def json_of(resp):
    try:
        return resp.json()
    except Exception as exc:
        raise AssertionError(
            f"Expected JSON response, got status={resp.status_code}, body={resp.text}"
        ) from exc


def register(session: requests.Session, base_url: str, username: str, password: str, display_name: str):
    return session.post(
        api_url(base_url, "/api/auth/register"),
        json={
            "username": username,
            "password": password,
            "display_name": display_name,
        },
        timeout=10,
    )


def create_course(session: requests.Session, base_url: str, title: str, description: str):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=10,
    )


def list_users(session: requests.Session, base_url: str):
    return session.get(api_url(base_url, "/api/users"), timeout=10)


def find_user_id_by_username(session: requests.Session, base_url: str, username: str):
    resp = list_users(session, base_url)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True
    for user in payload["users"]:
        if user["username"] == username:
            return int(user["user_id"])
    return None


def add_member(session: requests.Session, base_url: str, course_id: int, user_id: int, role_in_course: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={"user_id": user_id, "role_in_course": role_in_course},
        timeout=10,
    )


def create_question(session: requests.Session, base_url: str, course_id: int, qtype: str, prompt: str, options_json, answer_json):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/questions"),
        json={
            "qtype": qtype,
            "prompt": prompt,
            "options_json": options_json,
            "answer_json": answer_json,
        },
        timeout=10,
    )


def create_quiz(session: requests.Session, base_url: str, course_id: int, title: str, description: str, open_at: str = "", due_at: str = ""):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes"),
        json={
            "title": title,
            "description": description,
            "open_at": open_at,
            "due_at": due_at,
        },
        timeout=10,
    )


def configure_quiz_question(session: requests.Session, base_url: str, course_id: int, quiz_id: int, question_id: int, points: int, position: int):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/questions"),
        json={
            "question_id": question_id,
            "points": points,
            "position": position,
        },
        timeout=10,
    )


def start_attempt(session: requests.Session, base_url: str, course_id: int, quiz_id: int):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start"),
        json={},
        timeout=10,
    )


def answer_attempt(session: requests.Session, base_url: str, course_id: int, quiz_id: int, attempt_id: int, question_id: int, answer_json):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers"),
        json={
            "question_id": question_id,
            "answer_json": answer_json,
        },
        timeout=10,
    )


def submit_attempt(session: requests.Session, base_url: str, course_id: int, quiz_id: int, attempt_id: int):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit"),
        json={},
        timeout=10,
    )


def test_exploit_vuln_002_student_can_start_before_open_at(app_instance):
    """
    Exploit expectation:
    Student can start an attempt before the configured open_at time.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    student = requests.Session()

    r = register(teacher, base_url, "window_teacher_early", "pass123", "Window Teacher Early")
    assert r.status_code == 201, r.text

    r = register(student, base_url, "window_student_early", "pass123", "Window Student Early")
    assert r.status_code == 201, r.text

    course_resp = create_course(teacher, base_url, "Quiz Early Start Course", "demo")
    assert course_resp.status_code == 201, course_resp.text
    course_id = int(json_of(course_resp)["course"]["course_id"])

    student_user_id = find_user_id_by_username(teacher, base_url, "window_student_early")
    assert student_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, student_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    q_resp = create_question(
        teacher,
        base_url,
        course_id,
        "single_choice",
        "Capital of France?",
        json.dumps(["Paris", "Rome", "Berlin"]),
        json.dumps({"correct": "Paris"}),
    )
    assert q_resp.status_code == 201, q_resp.text
    question_id = int(json_of(q_resp)["question"]["question_id"])

    quiz_resp = create_quiz(
        teacher,
        base_url,
        course_id,
        "Future Quiz",
        "not open yet",
        open_at="2099-01-01T00:00:00+00:00",
        due_at="2099-01-02T00:00:00+00:00",
    )
    assert quiz_resp.status_code == 201, quiz_resp.text
    quiz_id = int(json_of(quiz_resp)["quiz"]["quiz_id"])

    cfg_resp = configure_quiz_question(teacher, base_url, course_id, quiz_id, question_id, 5, 1)
    assert cfg_resp.status_code in (200, 201), cfg_resp.text

    start_resp = start_attempt(student, base_url, course_id, quiz_id)
    assert start_resp.status_code == 201, start_resp.text
    payload = json_of(start_resp)
    assert payload["success"] is True
    assert payload["attempt"]["attempt"]["submitted_at"] is None


def test_exploit_vuln_002_student_can_answer_and_submit_after_due_at(app_instance):
    """
    Exploit expectation:
    Student can answer and submit even after the configured due_at time has passed.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    student = requests.Session()

    r = register(teacher, base_url, "window_teacher_late", "pass123", "Window Teacher Late")
    assert r.status_code == 201, r.text

    r = register(student, base_url, "window_student_late", "pass123", "Window Student Late")
    assert r.status_code == 201, r.text

    course_resp = create_course(teacher, base_url, "Quiz Late Submit Course", "demo")
    assert course_resp.status_code == 201, course_resp.text
    course_id = int(json_of(course_resp)["course"]["course_id"])

    student_user_id = find_user_id_by_username(teacher, base_url, "window_student_late")
    assert student_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, student_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    q_resp = create_question(
        teacher,
        base_url,
        course_id,
        "single_choice",
        "2 + 3 = ?",
        json.dumps(["4", "5", "6"]),
        json.dumps({"correct": "5"}),
    )
    assert q_resp.status_code == 201, q_resp.text
    question_id = int(json_of(q_resp)["question"]["question_id"])

    quiz_resp = create_quiz(
        teacher,
        base_url,
        course_id,
        "Expired Quiz",
        "already overdue",
        open_at="2000-01-01T00:00:00+00:00",
        due_at="2000-01-02T00:00:00+00:00",
    )
    assert quiz_resp.status_code == 201, quiz_resp.text
    quiz_id = int(json_of(quiz_resp)["quiz"]["quiz_id"])

    cfg_resp = configure_quiz_question(teacher, base_url, course_id, quiz_id, question_id, 5, 1)
    assert cfg_resp.status_code in (200, 201), cfg_resp.text

    start_resp = start_attempt(student, base_url, course_id, quiz_id)
    assert start_resp.status_code == 201, start_resp.text
    start_payload = json_of(start_resp)
    assert start_payload["success"] is True
    attempt_id = int(start_payload["attempt"]["attempt"]["attempt_id"])

    answer_resp = answer_attempt(
        student,
        base_url,
        course_id,
        quiz_id,
        attempt_id,
        question_id,
        json.dumps({"selected": "5"}),
    )
    assert answer_resp.status_code == 200, answer_resp.text
    answer_payload = json_of(answer_resp)
    assert answer_payload["success"] is True
    assert len(answer_payload["attempt"]["answers"]) == 1

    submit_resp = submit_attempt(student, base_url, course_id, quiz_id, attempt_id)
    assert submit_resp.status_code == 200, submit_resp.text
    submit_payload = json_of(submit_resp)
    assert submit_payload["success"] is True
    assert submit_payload["attempt"]["attempt"]["submitted_at"] is not None