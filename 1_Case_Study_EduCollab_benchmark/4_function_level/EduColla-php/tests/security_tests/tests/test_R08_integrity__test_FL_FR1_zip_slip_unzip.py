import uuid
import requests
import pytest

from util import (
    BASE_URL, rand,
    api_register, api_login,
    api_create_course, api_add_member,
    api_create_assignment, api_upload_file,
    api_create_submission, make_zip_bytes,
)


def test_zip_unzip_rejects_zip_slip_write_to_public():
    # admin login
    admin_s, r = api_login("admin", "admin123")
    assert r.status_code == 200, r.text

    # create course
    r = api_create_course(admin_s, title=rand("course"))
    assert r.status_code == 200, r.text
    course_id = r.json()["course"]["course_id"]

    # create assignment
    r = api_create_assignment(admin_s, course_id, title=rand("as"))
    assert r.status_code == 200, r.text
    assignment_id = r.json()["assignment"]["assignment_id"]

    # student register + login
    stu_u = rand("stu")
    api_register(stu_u, "pw", stu_u)
    stu_s, r = api_login(stu_u, "pw")
    assert r.status_code == 200, r.text

    # add student membership
    me = stu_s.get(f"{BASE_URL}/api/auth/me", timeout=10).json()["user"]
    r = api_add_member(admin_s, course_id, me["user_id"], "student")
    assert r.status_code == 200, r.text

    # Use a unique marker to avoid interference from previous runs
    marker = uuid.uuid4().hex[:10]
    target_name = f"pwned_{marker}.txt"
    public_url = f"{BASE_URL}/{target_name}"

    # Baseline: must NOT already exist (avoid false fail due to leftovers)
    baseline = requests.get(public_url, params={"_": marker}, timeout=10)
    assert baseline.status_code in (404, 400), (
        f"Baseline indicates {target_name} already accessible; environment not clean. "
        f"status={baseline.status_code} body={baseline.text[:200]!r}"
    )

    # upload malicious zip as admin (staff)
    zip_bytes = make_zip_bytes([
        (f"../../public/{target_name}", f"pwned-{marker}"),
        ("ok.txt", "ok"),
    ])
    r = api_upload_file(admin_s, course_id, "evil.zip", zip_bytes)
    assert r.status_code == 200, r.text
    upload_id = r.json()["upload"]["upload_id"]

    # student creates submission referencing that upload
    r = api_create_submission(stu_s, course_id, assignment_id, content_text="sub", attachment_upload_id=upload_id)
    assert r.status_code == 200, r.text
    submission_id = r.json()["submission"]["submission_id"]

    # unzip attempt:
    # Secure implementations may either:
    # - reject with 4xx, OR
    # - proceed but sanitize/skip traversal entries and still return 200.
    r = admin_s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}/unzip",
        timeout=10,
    )
    assert r.status_code in (200, 201, 204, 400, 403), (
        f"Unexpected unzip response: {r.status_code} body={r.text[:300]!r}"
    )

    # Final check: traversal payload must NOT be web-accessible
    resp = requests.get(public_url, params={"_": marker}, timeout=10)
    assert resp.status_code in (404, 400), (
        f"Possible Zip Slip: {target_name} became accessible under public/. "
        f"status={resp.status_code} body_head={resp.text[:200]!r}"
    )

    # Extra safety: even if server returns 200, it must not contain our marker content
    assert f"pwned-{marker}" not in (resp.text or ""), "Marker content leaked via web; traversal likely succeeded."