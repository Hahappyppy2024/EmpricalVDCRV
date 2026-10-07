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

def test_requirement_04_assignment_submission_upload(client, app_instance):
    _, base_url = client
    conn = open_db(app_instance["db_path"])

    admin = requests.Session()
    teacher = requests.Session()
    student = requests.Session()

    for s in (admin, teacher, student):
        s.headers.update({"Accept": "application/json"})

    try:
        ok(login(admin, base_url, "admin", "admin123"))
        ok(register(teacher, base_url, "req4_teacher", "pass123", "Req4 Teacher"))
        ok(register(student, base_url, "req4_student", "pass123", "Req4 Student"))

        teacher_id = get_user_id(conn, "req4_teacher")
        student_id = get_user_id(conn, "req4_student")
        assert teacher_id is not None
        assert student_id is not None

        ok(create_course(admin, base_url, "Req4 Course", "Assignment submission upload course"))
        course_id = get_course_id(conn, "Req4 Course")
        assert course_id is not None

        ok(add_member(admin, base_url, course_id, teacher_id, "teacher"))
        ok(add_member(admin, base_url, course_id, student_id, "student"))

        # create/list/get/update assignment
        due1 = "2026-12-01T00:00:00Z"
        r = teacher.post(
            api_url(base_url, f"/api/courses/{course_id}/assignments"),
            json={"title": "Req4 Assignment", "description": "Initial assignment", "due_at": due1},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT assignment_id FROM assignments WHERE course_id=? ORDER BY assignment_id DESC",
            (course_id,),
        ).fetchone()
        assert row is not None
        assignment_id = row["assignment_id"]

        ok(student.get(api_url(base_url, f"/api/courses/{course_id}/assignments"), timeout=5, allow_redirects=False))
        ok(student.get(api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}"), timeout=5, allow_redirects=False))

        due2 = "2026-12-02T00:00:00Z"
        r = teacher.put(
            api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}"),
            json={"title": "Req4 Assignment Updated", "description": "Updated assignment", "due_at": due2},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT title, description, due_at FROM assignments WHERE assignment_id=?",
            (assignment_id,),
        ).fetchone()
        assert row["title"] == "Req4 Assignment Updated"
        assert row["description"] == "Updated assignment"
        assert row["due_at"] == due2

        # upload/list/download/delete
        files = {"file": ("req4.txt", b"req4 file content", "text/plain")}
        r = teacher.post(
            api_url(base_url, f"/api/courses/{course_id}/uploads"),
            files=files,
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT upload_id, original_name, storage_path FROM uploads WHERE course_id=? ORDER BY upload_id DESC",
            (course_id,),
        ).fetchone()
        assert row is not None
        upload_id = row["upload_id"]
        assert row["original_name"] == "req4.txt"
        assert row["storage_path"]

        ok(student.get(api_url(base_url, f"/api/courses/{course_id}/uploads"), timeout=5, allow_redirects=False))

        r = student.get(
            api_url(base_url, f"/api/courses/{course_id}/uploads/{upload_id}/download"),
            timeout=5,
            allow_redirects=False,
        )
        assert r.status_code in (200, 302)
        if r.status_code == 200:
            assert r.content == b"req4 file content"

        # submission create/update/list mine/list for assignment
        r = student.post(
            api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions"),
            json={"content_text": "req4 submission text", "attachment_upload_id": None},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT submission_id, content_text FROM submissions WHERE course_id=? AND assignment_id=? AND student_id=?",
            (course_id, assignment_id, student_id),
        ).fetchone()
        assert row is not None
        submission_id = row["submission_id"]
        assert row["content_text"] == "req4 submission text"

        r = student.put(
            api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}"),
            json={"content_text": "req4 submission updated", "attachment_upload_id": None},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT content_text FROM submissions WHERE submission_id=?",
            (submission_id,),
        ).fetchone()
        assert row["content_text"] == "req4 submission updated"

        ok(student.get(api_url(base_url, "/api/my/submissions"), timeout=5, allow_redirects=False))
        ok(teacher.get(api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions"), timeout=5, allow_redirects=False))

        r = teacher.delete(
            api_url(base_url, f"/api/courses/{course_id}/uploads/{upload_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT upload_id FROM uploads WHERE upload_id=?",
            (upload_id,),
        ).fetchone()
        assert row is None

        r = teacher.delete(
            api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT assignment_id FROM assignments WHERE assignment_id=?",
            (assignment_id,),
        ).fetchone()
        assert row is None
    finally:
        conn.close()