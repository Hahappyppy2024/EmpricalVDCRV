import re

# ============================================================
# API (JSON) - Mishandling Exceptional Conditions
# ============================================================

def _text(rv):
    try:
        return rv.get_data(as_text=True)
    except Exception:
        return (rv.data or b"").decode("utf-8", errors="ignore")


def _no_leak(text):
    forbidden = [
        r"Traceback", r"Werkzeug", r"Debugger",
        r"File \"[^\"]+\.py\"", r"/[A-Za-z0-9_\-./]+\.py", r"\\[A-Za-z0-9_\-\\.]+\.py",
        r"sqlite3\.", r"OperationalError", r"SECRET_KEY",
    ]
    for pat in forbidden:
        assert re.search(pat, text, re.I) is None, f"Leakage matched /{pat}/"


def test_api_malformed_json_returns_4xx_and_no_leak(client, auth, sample_course):
    # Ensure authenticated (some APIs require auth)
    auth.register("exc_api_user", "pass", "Exc API User")
    auth.login("exc_api_user", "pass")

    # Choose a common JSON endpoint (course creation)
    rv = client.post(
        "/api/courses",
        data='{"title":',
        headers={"Content-Type": "application/json"},
        follow_redirects=False,
    )
    if rv.status_code == 404:
        # API not implemented in this variant
        return

    assert rv.status_code in (400, 415, 422), f"Expected 4xx for malformed JSON, got {rv.status_code}"
    _no_leak(_text(rv))


def test_api_upload_missing_file_is_4xx_and_no_leak(client, auth, sample_course):
    course_id = sample_course
    # Login as teacher (already created in sample_course fixture)
    auth.login("teacher", "password")

    rv = client.post(
        f"/api/courses/{course_id}/uploads",
        data={},  # missing 'file'
        content_type="multipart/form-data",
        follow_redirects=False,
    )

    if rv.status_code == 404:
        return

    assert rv.status_code in (400, 401, 403, 415, 422), f"Expected 4xx for missing file, got {rv.status_code}"
    _no_leak(_text(rv))
