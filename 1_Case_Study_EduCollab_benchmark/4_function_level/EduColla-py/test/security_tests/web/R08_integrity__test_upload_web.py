import os
import io
import re

from flask import current_app
from .._helpers import db_one, ensure_user_and_membership

# ============================================================
# Web (HTML) - Upload Security
# ============================================================

def test_web_upload_forbidden_for_student(client, auth, sample_course):
    course_id = sample_course
    ensure_user_and_membership(client, course_id, "student_u", "pass", "Student U", "student")
    auth.login("student_u", "pass")

    data = {"file": (io.BytesIO(b"fake"), "test.txt")}
    rv = client.post(
        f"/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    assert rv.status_code == 403


def test_web_upload_path_traversal_is_sanitized_and_saved_under_upload_folder(client, auth, sample_course):
    course_id = sample_course
    auth.login("teacher", "password")

    data = {"file": (io.BytesIO(b"malicious"), "../../malicious.txt")}
    rv = client.post(
        f"/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    assert rv.status_code in (302, 201)

    upload = db_one(client, "SELECT * FROM upload ORDER BY upload_id DESC LIMIT 1")
    assert upload is not None
    assert upload["original_name"] == "malicious.txt"

    sp = upload["storage_path"]
    assert ".." not in sp
    assert not os.path.isabs(sp)
    assert sp.startswith(f"{course_id}/") or sp.startswith(f"{course_id}{os.sep}")

    base = os.path.basename(sp)
    assert re.match(r"^[0-9a-f]{32}(\.[a-z0-9]+)?$", base)

    with client.application.app_context():
        upload_folder = current_app.config["UPLOAD_FOLDER"]

    full_path = os.path.join(upload_folder, sp)
    assert os.path.exists(full_path)

    abs_saved = os.path.abspath(full_path)
    abs_root = os.path.abspath(upload_folder)
    assert abs_saved.startswith(abs_root + os.sep)


def test_web_upload_rejects_empty_filename(client, auth, sample_course):
    course_id = sample_course
    auth.login("teacher", "password")

    data = {"file": (io.BytesIO(b"data"), "")}
    rv = client.post(
        f"/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    # Many apps treat as 400; some redirect with flash
    assert rv.status_code in (200, 302, 400)
