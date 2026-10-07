from helpers import (
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    upload_file,
    json_of,
)


def test_requirement_04_assignment_submission_upload(client, app_instance):
    conn = open_db()
    try:
        ok(login(client, "admin", "admin123"))

        ok(register(client, "req4_teacher", "pass123", "Req4 Teacher"))
        ok(register(client, "req4_student", "pass123", "Req4 Student"))

        teacher_id = get_user_id(conn, "req4_teacher")
        student_id = get_user_id(conn, "req4_student")
        assert teacher_id is not None
        assert student_id is not None

        ok(create_course(client, "Req4 Course", "Assignment submission upload course"))
        course_id = get_course_id(conn, "Req4 Course")
        assert course_id is not None

        ok(add_member(client, course_id, teacher_id, "teacher"))
        ok(add_member(client, course_id, student_id, "student"))

        # login as teacher
        ok(login(client, "req4_teacher", "pass123"))

        # create assignment
        r = client.post(
            f"/api/courses/{course_id}/assignments",
            json={
                "title": "Req4 Assignment",
                "description": "Initial assignment",
                "due_at": "2026-12-01T00:00:00Z",
            },
        )
        ok(r)
        assignment_id = json_of(r)["assignment_id"]

        # list assignments
        r = client.get(f"/api/courses/{course_id}/assignments")
        ok(r)
        assert any(a["assignment_id"] == assignment_id for a in json_of(r)["assignments"])

        # get assignment
        r = client.get(f"/api/courses/{course_id}/assignments/{assignment_id}")
        ok(r)
        assert json_of(r)["assignment"]["assignment_id"] == assignment_id

        # update assignment
        r = client.put(
            f"/api/courses/{course_id}/assignments/{assignment_id}",
            json={
                "title": "Req4 Assignment Updated",
                "description": "Updated assignment",
                "due_at": "2026-12-02T00:00:00Z",
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT title, description FROM assignments WHERE assignment_id=?",
            (assignment_id,),
        ).fetchone()
        assert row["title"] == "Req4 Assignment Updated"
        assert row["description"] == "Updated assignment"

        # upload file
        r = upload_file(client, course_id, "req4.txt", b"req4 file content")
        ok(r)
        upload_id = json_of(r)["upload_id"]

        # list uploads
        r = client.get(f"/api/courses/{course_id}/uploads")
        ok(r)
        assert any(u["upload_id"] == upload_id for u in json_of(r)["uploads"])

        # download upload
        r = client.get(f"/api/courses/{course_id}/uploads/{upload_id}/download")
        assert r.status_code == 200
        assert b"req4 file content" in r.data

        # login as student
        ok(login(client, "req4_student", "pass123"))

        # create submission
        r = client.post(
            f"/api/courses/{course_id}/assignments/{assignment_id}/submissions",
            json={
                "content_text": "req4 submission text",
                "attachment_upload_id": None,
            },
        )
        ok(r)
        submission_id = json_of(r)["submission_id"]

        # update submission
        r = client.put(
            f"/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}",
            json={
                "content_text": "req4 submission updated",
                "attachment_upload_id": None,
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT content_text FROM submissions WHERE submission_id=?",
            (submission_id,),
        ).fetchone()
        assert row["content_text"] == "req4 submission updated"

        # list my submissions
        r = client.get("/api/my/submissions")
        ok(r)
        assert "submissions" in json_of(r)

        # login as teacher
        ok(login(client, "req4_teacher", "pass123"))

        # list submissions for assignment
        r = client.get(f"/api/courses/{course_id}/assignments/{assignment_id}/submissions")
        ok(r)
        assert "submissions" in json_of(r)

        # delete upload
        r = client.delete(f"/api/courses/{course_id}/uploads/{upload_id}")
        ok(r)

        # delete assignment
        r = client.delete(f"/api/courses/{course_id}/assignments/{assignment_id}")
        ok(r)
    finally:
        conn.close()