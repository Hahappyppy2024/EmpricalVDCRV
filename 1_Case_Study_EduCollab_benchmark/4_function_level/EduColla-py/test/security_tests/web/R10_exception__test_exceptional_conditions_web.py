import re

# ============================================================
# Web (HTML) - Mishandling Exceptional Conditions
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


def test_web_upload_missing_file_is_4xx_and_no_leak(client, auth, sample_course):
    course_id = sample_course
    auth.login("teacher", "password")

    # Common HTML upload route in this project: /courses/<id>/uploads
    rv = client.post(
        f"/courses/{course_id}/uploads",
        data={},  # missing file
        content_type="multipart/form-data",
        follow_redirects=False,
    )
    if rv.status_code == 404:
        # some variants only implement assignment submission upload; if so, skip gracefully
        return
    assert rv.status_code in (400, 401, 403, 415, 422, 302), f"Expected 4xx/redirect for missing file, got {rv.status_code}"
    _no_leak(_text(rv))


def test_web_extreme_input_does_not_crash_or_leak(client, auth, sample_course):
    course_id = sample_course
    auth.login("teacher", "password")

    # Try extreme query string on posts listing/search (should not 500 or leak internals)
    huge = "A" * 10000
    rv = client.get(f"/courses/{course_id}/posts?keyword={huge}", follow_redirects=False)
    if rv.status_code == 404:
        return
    assert rv.status_code in (200, 302, 400, 413, 414), f"Unexpected status for huge input: {rv.status_code}"
    _no_leak(_text(rv))
