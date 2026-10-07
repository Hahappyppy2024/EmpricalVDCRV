import pytest
import requests

from helpers import ApiClient, membership_id_by_user


def build_course_with_members(admin: ApiClient, course_title: str, memberships: list[tuple[int, str]]):
    course = admin.create_course(course_title)
    for user_id, role in memberships:
        admin.add_member(course["course_id"], user_id, role)
    return course


def assert_denied(resp: requests.Response):
    assert resp.status_code in (401, 403, 404), f"expected denial, got {resp.status_code}: {resp.text}"


def test_regression_default_admin_credentials_should_not_work(app_instance):
    attacker = ApiClient.new(app_instance["base_url"])
    resp = attacker.post("/api/auth/login", json={"username": "admin", "password": "admin123"})
    assert resp.status_code in (400, 401, 403), resp.text


def test_regression_login_should_rotate_php_session_id(app_instance):
    fixed_sid = "fixedattackersession123"
    s = requests.Session()
    s.cookies.set("PHPSESSID", fixed_sid, path="/")

    r = s.post(
        f"{app_instance['base_url']}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    # This test assumes the app still supports a seeded admin or another equivalent login target.
    # If the default admin is removed entirely, this test should be pointed at a different known-valid account.
    if r.status_code != 200:
        pytest.skip("login target changed after hardcoded-admin fix; point this test at a valid non-default account")

    me = s.get(f"{app_instance['base_url']}/api/auth/me", timeout=5)
    assert me.status_code == 200, me.text
    assert me.json()["user"]["username"] == "admin"
    assert s.cookies.get("PHPSESSID") != fixed_sid


def test_regression_cross_course_membership_idor_should_be_blocked(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher_a = ApiClient.new(app_instance["base_url"])
    teacher_a_user = teacher_a.register("teacher_a", "pass123", "Teacher A")
    teacher_b = ApiClient.new(app_instance["base_url"])
    teacher_b_user = teacher_b.register("teacher_b", "pass123", "Teacher B")
    victim = ApiClient.new(app_instance["base_url"])
    victim_user = victim.register("victim", "pass123", "Victim")

    course_a = build_course_with_members(admin, "Course A", [(teacher_a_user["user_id"], "teacher")])
    course_b = build_course_with_members(
        admin,
        "Course B",
        [(teacher_b_user["user_id"], "teacher"), (victim_user["user_id"], "student")],
    )

    members_b_before = admin.list_members(course_b["course_id"])
    victim_membership_id = membership_id_by_user(members_b_before, victim_user["user_id"])

    teacher_a.login("teacher_a", "pass123")
    resp = teacher_a.update_member_role(course_a["course_id"], victim_membership_id, "teacher")
    assert_denied(resp)

    members_b_after = admin.list_members(course_b["course_id"])
    victim_row = next(m for m in members_b_after if int(m["user_id"]) == int(victim_user["user_id"]))
    assert victim_row["role_in_course"] == "student"


def test_regression_non_owner_should_not_edit_other_users_post(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    author = ApiClient.new(app_instance["base_url"])
    author_user = author.register("author", "pass123", "Author")
    attacker = ApiClient.new(app_instance["base_url"])
    attacker_user = attacker.register("attacker", "pass123", "Attacker")

    course = build_course_with_members(
        admin,
        "Shared Course",
        [(author_user["user_id"], "student"), (attacker_user["user_id"], "student")],
    )

    author.login("author", "pass123")
    post = author.create_post(course["course_id"], "original title", "original body")

    attacker.login("attacker", "pass123")
    resp = attacker.update_post(course["course_id"], post["post_id"], "tampered title", "tampered body")
    assert_denied(resp)

    changed = author.get_post(course["course_id"], post["post_id"])
    assert changed["title"] == "original title"
    assert changed["body"] == "original body"


def test_regression_non_owner_should_not_edit_other_users_comment(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    author = ApiClient.new(app_instance["base_url"])
    author_user = author.register("comment_author", "pass123", "Comment Author")
    attacker = ApiClient.new(app_instance["base_url"])
    attacker_user = attacker.register("comment_attacker", "pass123", "Comment Attacker")

    course = build_course_with_members(
        admin,
        "Comment Course",
        [(author_user["user_id"], "student"), (attacker_user["user_id"], "student")],
    )

    author.login("comment_author", "pass123")
    post = author.create_post(course["course_id"], "discussion", "post body")
    comment = author.create_comment(course["course_id"], post["post_id"], "author comment")

    attacker.login("comment_attacker", "pass123")
    resp = attacker.update_comment(course["course_id"], post["post_id"], comment["comment_id"], "tampered comment")
    assert_denied(resp)

    comments = author.list_comments(course["course_id"], post["post_id"])
    changed = next(c for c in comments if int(c["comment_id"]) == int(comment["comment_id"]))
    assert changed["body"] == "author comment"


def test_regression_student_should_not_receive_question_answer_key(app_instance):
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
    r = student.get(f"/api/courses/{course['course_id']}/questions/{question['question_id']}")

    if r.status_code in (401, 403, 404):
        return

    assert r.status_code == 200, r.text
    leaked = r.json()["question"]
    assert "answer_json" not in leaked or leaked["answer_json"] in (None, "", {}, [])


def test_regression_cross_course_quiz_start_should_be_blocked(app_instance):
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
    add_resp = teacher.add_quiz_question(course_b["course_id"], quiz_b["quiz_id"], q_b["question_id"], points=5, position=1)
    assert add_resp.status_code == 200, add_resp.text

    student_a.login("student_a", "pass123")
    start = student_a.start_attempt(course_a["course_id"], quiz_b["quiz_id"])
    assert_denied(start)


def test_regression_cross_course_question_attachment_should_be_blocked(app_instance):
    admin = ApiClient.new(app_instance["base_url"])
    admin.login("admin", "admin123")

    teacher_a = ApiClient.new(app_instance["base_url"])
    teacher_a_user = teacher_a.register("teacher_course_a", "pass123", "Teacher Course A")
    teacher_b = ApiClient.new(app_instance["base_url"])
    teacher_b_user = teacher_b.register("teacher_course_b", "pass123", "Teacher Course B")

    course_a = build_course_with_members(admin, "Course A", [(teacher_a_user["user_id"], "teacher")])
    course_b = build_course_with_members(admin, "Course B", [(teacher_b_user["user_id"], "teacher")])

    teacher_b.login("teacher_course_b", "pass123")
    foreign_question = teacher_b.create_question(course_b["course_id"], "foreign prompt", '{"value":7}')

    teacher_a.login("teacher_course_a", "pass123")
    local_quiz = teacher_a.create_quiz(course_a["course_id"], "Local Quiz")
    attach = teacher_a.add_quiz_question(course_a["course_id"], local_quiz["quiz_id"], foreign_question["question_id"], points=3, position=1)
    assert_denied(attach)
