import os
import re
import requests
import uuid

from common import (
    api_json,
    assert_ok,
    login,
    register_student,
    create_course,
)


BASE_URL = os.getenv('BASE_URL', 'http://localhost:8000').rstrip('/')


def _extract_token(invite_link: str) -> str:
    m = re.search(r"token=([A-Za-z0-9_\-]+)", invite_link)
    assert m, f"No token in invite_link: {invite_link}"
    return m.group(1)


def test_rl_invite_link_join_flow_functional():
    # Admin creates course and generates invite
    admin = requests.Session()
    login(admin, BASE_URL, 'admin', 'admin123')
    course = create_course(admin, BASE_URL, 'Invite Course', 'desc')
    course_id = course['course_id']

    r = api_json(admin, 'POST', f"{BASE_URL}/api/courses/{course_id}/invites",
                json={"role_in_course": "student", "ttl_minutes": 60})
    assert_ok(r, (200,))
    data = r.json()
    invite_link = data['invite_link']
    token = _extract_token(invite_link)

    # New student registers + joins using token
    stu = requests.Session()
    username = 'stu_invite_' + uuid.uuid4().hex[:8]
    password = 'pass1234'
    register_student(stu, BASE_URL, username, password, 'Student Invite')
    login(stu, BASE_URL, username, password)

    r = api_json(stu, 'POST', f"{BASE_URL}/api/join", json={"token": token})
    assert_ok(r, (200,))
    out = r.json()
    assert out.get('joined') is True
    assert out.get('course_id') == course_id

    # Memberships should include the course
    r = api_json(stu, 'GET', f"{BASE_URL}/api/memberships")
    assert_ok(r, (200,))
    memberships = r.json().get('memberships', [])
    assert any(m.get('course_id') == course_id for m in memberships)
