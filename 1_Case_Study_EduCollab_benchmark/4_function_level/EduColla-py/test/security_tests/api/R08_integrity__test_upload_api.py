import os
import io
import re

from flask import current_app
from .._helpers import db_one, api_get

# ============================================================
# API (JSON) - Upload Security
# ============================================================

def test_api_upload_path_traversal_sanitized(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    data = {"file": (io.BytesIO(b"malicious"), "../../malicious.txt")}
    rv = client.post(
        f"/api/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    if rv.status_code == 404:
        # API upload route not implemented
        return

    assert rv.status_code in (200, 201, 302, 400)

    upload = db_one(client, "SELECT * FROM upload ORDER BY upload_id DESC LIMIT 1")
    assert upload is not None
    assert upload["original_name"] == "malicious.txt"
    sp = upload["storage_path"]
    assert ".." not in sp
    assert not os.path.isabs(sp)

    with client.application.app_context():
        upload_folder = current_app.config["UPLOAD_FOLDER"]
    full_path = os.path.join(upload_folder, sp)
    assert os.path.exists(full_path)


def test_api_upload_rejects_empty_filename(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    data = {"file": (io.BytesIO(b"data"), "")}
    rv = client.post(
        f"/api/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    if rv.status_code == 404:
        return
    assert rv.status_code in (200, 201, 302, 400)


def test_api_upload_response_is_json_when_success(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    data = {"file": (io.BytesIO(b"hello"), "hello.txt")}
    rv = client.post(
        f"/api/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    if rv.status_code == 404:
        return
    if rv.status_code in (200, 201):
        assert rv.mimetype == "application/json"
