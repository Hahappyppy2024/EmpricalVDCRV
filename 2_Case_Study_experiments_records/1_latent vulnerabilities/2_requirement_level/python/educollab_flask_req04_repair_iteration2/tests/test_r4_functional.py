from io import BytesIO
import sqlite3

from conftest import api_login, api_logout, api_register


def get_user_id(app_instance, username):
    db = sqlite3.connect(app_instance["db_path"])
    try:
        row = db.execute("SELECT user_id FROM users WHERE username=?", (username,)).fetchone()
        return row[0] if row else None
    finally:
        db.close()


def get_membership_id(app_instance, course_id, user_id):
    db = sqlite3.connect(app_instance["db_path"])
    try:
        row = db.execute(
            "SELECT membership_id FROM memberships WHERE course_id=? AND user_id=?",
            (course_id, user_id),
        ).fetchone()
        return row[0] if row else None
    finally:
        db.close()


def add_member(client, course_id, user_id, role_in_course="student"):
    return client.post(
        f"/api/courses/{course_id}/members",
        json={"user_id": user_id, "role_in_course": role_in_course},
    )


def test_r4_assignment_upload_submission_flow(client, app_instance):
    api_login(client, "admin", "admin123")

    course_res = client.post(
        "/api/courses",
        json={"title": "R4 Course", "description": "Assignments"},
    )
    assert course_res.status_code == 201
    course_id = course_res.get_json()["course"]["course_id"]

    reg_student = api_register(client, "student_r4")
    assert reg_student.status_code == 201
    student_id = reg_student.get_json()["user"]["user_id"]

    api_logout(client)
    api_login(client, "admin", "admin123")
    add_member_res = add_member(client, course_id, student_id, "student")
    assert add_member_res.status_code in (200, 201)

    create_assignment_res = client.post(
        f"/api/courses/{course_id}/assignments",
        json={
            "title": "HW1",
            "description": "Solve task",
            "due_at": "2026-03-31 23:59",
        },
    )
    assert create_assignment_res.status_code == 201
    assignment = create_assignment_res.get_json()["assignment"]
    assignment_id = assignment["assignment_id"]
    assert assignment["title"] == "HW1"

    list_assignments_res = client.get(f"/api/courses/{course_id}/assignments")
    assert list_assignments_res.status_code == 200
    listed_assignments = list_assignments_res.get_json()["assignments"]
    assert any(item["assignment_id"] == assignment_id for item in listed_assignments)

    update_assignment_res = client.put(
        f"/api/courses/{course_id}/assignments/{assignment_id}",
        json={
            "title": "HW1 updated",
            "description": "Revised",
            "due_at": "2026-04-01 10:00",
        },
    )
    assert update_assignment_res.status_code == 200
    assert update_assignment_res.get_json()["assignment"]["title"] == "HW1 updated"

    upload_res = client.post(
        f"/api/courses/{course_id}/uploads",
        data={"file": (BytesIO(b"teacher handout"), "handout.txt")},
        content_type="multipart/form-data",
    )
    assert upload_res.status_code == 201
    upload = upload_res.get_json()["upload"]
    upload_id = upload["upload_id"]
    assert upload["original_name"] == "handout.txt"

    api_logout(client)
    student_client = app_instance["app"].test_client()
    api_login(student_client, "student_r4")

    student_assignments_res = student_client.get(f"/api/courses/{course_id}/assignments")
    assert student_assignments_res.status_code == 200
    assert student_assignments_res.get_json()["assignments"][0]["assignment_id"] == assignment_id

    student_uploads_res = student_client.get(f"/api/courses/{course_id}/uploads")
    assert student_uploads_res.status_code == 200
    assert any(item["upload_id"] == upload_id for item in student_uploads_res.get_json()["uploads"])

    create_submission_res = student_client.post(
        f"/api/courses/{course_id}/assignments/{assignment_id}/submissions",
        json={
            "content_text": "my answer",
            "attachment_upload_id": upload_id,
        },
    )
    assert create_submission_res.status_code == 201
    submission = create_submission_res.get_json()["submission"]
    submission_id = submission["submission_id"]
    assert submission["student_id"] == student_id
    assert submission["attachment_upload_id"] == upload_id

    my_submissions_res = student_client.get("/api/my/submissions")
    assert my_submissions_res.status_code == 200
    mine = my_submissions_res.get_json()["submissions"]
    assert any(item["submission_id"] == submission_id and item["course_id"] == course_id for item in mine)

    update_submission_res = student_client.put(
        f"/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}",
        json={
            "content_text": "updated answer",
            "attachment_upload_id": upload_id,
        },
    )
    assert update_submission_res.status_code == 200
    assert update_submission_res.get_json()["submission"]["content_text"] == "updated answer"

    api_logout(student_client)

    api_login(client, "admin", "admin123")
    staff_view_res = client.get(f"/api/courses/{course_id}/assignments/{assignment_id}/submissions")
    assert staff_view_res.status_code == 200
    submissions = staff_view_res.get_json()["submissions"]
    assert any(item["submission_id"] == submission_id and item["student_id"] == student_id for item in submissions)

    download_res = client.get(f"/api/courses/{course_id}/uploads/{upload_id}/download")
    assert download_res.status_code == 200
    body = download_res.data
    assert b"teacher handout" in body
    download_res.close()
    del download_res

    delete_upload_res = client.delete(f"/api/courses/{course_id}/uploads/{upload_id}")
    assert delete_upload_res.status_code == 200
    assert delete_upload_res.get_json()["deleted_upload_id"] == upload_id

    delete_assignment_res = client.delete(f"/api/courses/{course_id}/assignments/{assignment_id}")
    assert delete_assignment_res.status_code == 200
    assert delete_assignment_res.get_json()["deleted_assignment_id"] == assignment_id


def test_r4_student_cannot_use_staff_only_assignment_or_upload_management(client, app_instance):
    api_login(client, "admin", "admin123")
    course_id = client.post(
        "/api/courses",
        json={"title": "R4 Authz", "description": "x"},
    ).get_json()["course"]["course_id"]

    reg_student = api_register(client, "student_r4_b")
    student_id = reg_student.get_json()["user"]["user_id"]

    api_logout(client)
    api_login(client, "admin", "admin123")
    add_member_res = add_member(client, course_id, student_id, "student")
    assert add_member_res.status_code in (200, 201)

    api_logout(client)
    student_client = app_instance["app"].test_client()
    api_login(student_client, "student_r4_b")

    create_assignment_res = student_client.post(
        f"/api/courses/{course_id}/assignments",
        json={"title": "Should fail", "description": "x", "due_at": ""},
    )
    assert create_assignment_res.status_code == 403
    assert create_assignment_res.get_json()["error"] == "course_staff_required"

    upload_res = student_client.post(
        f"/api/courses/{course_id}/uploads",
        data={"file": (BytesIO(b"x"), "x.txt")},
        content_type="multipart/form-data",
    )
    assert upload_res.status_code == 403
    assert upload_res.get_json()["error"] == "course_staff_required"

    api_logout(student_client)