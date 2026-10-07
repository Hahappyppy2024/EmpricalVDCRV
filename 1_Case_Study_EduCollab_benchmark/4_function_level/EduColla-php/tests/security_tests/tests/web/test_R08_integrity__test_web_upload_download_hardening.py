\
"""
Web (HTML) Security Tests — Upload/Download Hardening (HTML routes)

These tests verify that file-download responses are hardened:
- Content-Disposition filename is sanitized to basename (prevents header/path injection).
- X-Content-Type-Options: nosniff is present (prevents content sniffing issues in some contexts).

Note: This targets HTML routes (non-/api/):
- POST /courses/{course_id}/uploads
- GET  /courses/{course_id}/uploads/{upload_id}/download
"""

import io
from common import api_login_admin, api_create_course, html_post_form_raw, BASE_URL


def test_web_download_content_disposition_uses_basename_only():
    """
    Tests: Path traversal in uploaded filename does not propagate to download filename.

    Steps:
    - Upload a file with filename '../evil.txt' through HTML route.
    - Download it through HTML route.
    - Assert Content-Disposition uses 'evil.txt' and does not include '..', '/', or '\\'.
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="upload-course")

    # HTML upload uses multipart; requests handles it via 'files='
    files = {"file": ("../evil.txt", io.BytesIO(b"hello"), "text/plain")}
    r = admin.post(f"{BASE_URL}/courses/{course['course_id']}/uploads", files=files, timeout=10, allow_redirects=False)
    # Many apps redirect after upload (302). Accept both 200 and 302.
    assert r.status_code in (200, 302), r.text

    # If redirected, follow to list page and attempt to locate the latest upload via HTML is hard.
    # Instead, rely on the fact that your app also exposes an API to list uploads:
    api_list = admin.get(f"{BASE_URL}/api/courses/{course['course_id']}/uploads", timeout=10)
    assert api_list.status_code == 200, api_list.text
    uploads = api_list.json().get("uploads", [])
    assert len(uploads) >= 1, "No uploads found; ensure upload route saves records"
    up = uploads[-1]

    r = admin.get(
        f"{BASE_URL}/courses/{course['course_id']}/uploads/{up['upload_id']}/download",
        timeout=10,
        allow_redirects=False,
    )
    assert r.status_code == 200
    cd = r.headers.get("Content-Disposition", "")
    assert "attachment" in cd.lower()
    assert "evil.txt" in cd
    assert ".." not in cd
    assert "/" not in cd and "\\" not in cd


def test_web_download_sets_nosniff_header():
    """
    Tests: Download response sets X-Content-Type-Options: nosniff.

    This test is designed to fail if the header is missing, surfacing a common hardening gap.
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="nosniff-course")

    files = {"file": ("note.html", io.BytesIO(b"<h1>hi</h1>"), "text/html")}
    r = admin.post(f"{BASE_URL}/courses/{course['course_id']}/uploads", files=files, timeout=10, allow_redirects=False)
    assert r.status_code in (200, 302), r.text

    api_list = admin.get(f"{BASE_URL}/api/courses/{course['course_id']}/uploads", timeout=10)
    assert api_list.status_code == 200, api_list.text
    uploads = api_list.json().get("uploads", [])
    assert len(uploads) >= 1
    up = uploads[-1]

    r = admin.get(
        f"{BASE_URL}/courses/{course['course_id']}/uploads/{up['upload_id']}/download",
        timeout=10,
    )
    assert r.status_code == 200

    assert r.headers.get("X-Content-Type-Options", "").lower() == "nosniff"
