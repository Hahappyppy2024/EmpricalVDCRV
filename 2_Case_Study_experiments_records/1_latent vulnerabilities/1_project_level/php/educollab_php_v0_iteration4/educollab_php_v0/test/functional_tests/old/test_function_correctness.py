from pathlib import Path


def api_url(client, path: str) -> str:
    return client.base_url + path


def register(client, username: str, password: str = 'pass123', display_name: str | None = None):
    payload = {
        'username': username,
        'password': password,
        'display_name': display_name or username,
    }
    r = client.post(api_url(client, '/api/auth/register'), json=payload, timeout=5)
    assert r.status_code == 200, r.text
    return r.json()['user']


def login(client, username: str, password: str = 'pass123'):
    r = client.post(api_url(client, '/api/auth/login'), json={'username': username, 'password': password}, timeout=5)
    assert r.status_code == 200, r.text
    return r.json()['user']


def logout(client):
    r = client.post(api_url(client, '/api/auth/logout'), timeout=5)
    assert r.status_code == 200, r.text


def create_course(client, title='Course A', description='Desc'):
    r = client.post(api_url(client, '/api/courses'), json={'title': title, 'description': description}, timeout=5)
    assert r.status_code == 200, r.text
    return r.json()['course']


def add_member(client, course_id: int, user_id: int, role='student'):
    r = client.post(api_url(client, f'/api/courses/{course_id}/members'), json={'user_id': user_id, 'role_in_course': role}, timeout=5)
    assert r.status_code == 200, r.text
    return r.json()['membership']


def test_auth_bootstrap_and_admin_listing(client):
    me_unauth = client.get(api_url(client, '/api/auth/me'), timeout=5)
    assert me_unauth.status_code == 401

    user = register(client, 'alice', display_name='Alice')
    assert user['username'] == 'alice'

    me = client.get(api_url(client, '/api/auth/me'), timeout=5)
    assert me.status_code == 200
    assert me.json()['user']['display_name'] == 'Alice'

    not_admin = client.get(api_url(client, '/api/users'), timeout=5)
    assert not_admin.status_code == 403

    logout(client)
    admin = login(client, 'admin', 'admin123')
    assert admin['username'] == 'admin'

    users = client.get(api_url(client, '/api/users'), timeout=5)
    assert users.status_code == 200, users.text
    usernames = {u['username'] for u in users.json()['users']}
    assert 'admin' in usernames
    assert 'alice' in usernames

def test_course_membership_and_role_update_flow(client):
    teacher = register(client, 'teacher1')
    course = create_course(client, 'Distributed Systems', 'Spring section')
    course_id = course['course_id']

    members = client.get(api_url(client, f'/api/courses/{course_id}/members'), timeout=5)
    assert members.status_code == 200
    listed = members.json()['members']
    assert any(m['user_id'] == teacher['user_id'] and m['role_in_course'] == 'teacher' for m in listed)

    logout(client)
    student = register(client, 'student1')
    logout(client)
    login(client, 'teacher1')

    membership = add_member(client, course_id, student['user_id'], 'student')
    membership_id = membership['membership_id']

    updated = client.put(
        api_url(client, f'/api/courses/{course_id}/members/{membership_id}'),
        json={'role_in_course': 'assistant'},
        timeout=5,
    )
    assert updated.status_code == 200, updated.text
    assert updated.json()['membership']['role_in_course'] == 'assistant'

    members_after_update = client.get(api_url(client, f'/api/courses/{course_id}/members'), timeout=5)
    assert members_after_update.status_code == 200, members_after_update.text
    listed_after_update = members_after_update.json()['members']
    target = next(m for m in listed_after_update if m['membership_id'] == membership_id)
    assert target['role_in_course'] == 'assistant'

    removed = client.delete(api_url(client, f'/api/courses/{course_id}/members/{membership_id}'), timeout=5)
    assert removed.status_code == 200, removed.text

    members_after = client.get(api_url(client, f'/api/courses/{course_id}/members'), timeout=5)
    ids_after = {m['user_id'] for m in members_after.json()['members']}
    assert student['user_id'] not in ids_after


def test_posts_comments_and_search_flow(client):
    teacher = register(client, 'teacher_posts')
    course = create_course(client, 'Networks', 'Lab course')
    course_id = course['course_id']

    post_res = client.post(
        api_url(client, f'/api/courses/{course_id}/posts'),
        json={'title': 'Exam review', 'body': 'Focus on routing and congestion control'},
        timeout=5,
    )
    assert post_res.status_code == 200, post_res.text
    post = post_res.json()['post']
    post_id = post['post_id']

    comment_res = client.post(
        api_url(client, f'/api/courses/{course_id}/posts/{post_id}/comments'),
        json={'body': 'Need more examples for congestion control.'},
        timeout=5,
    )
    assert comment_res.status_code == 200, comment_res.text
    comment_id = comment_res.json()['comment']['comment_id']

    get_post = client.get(api_url(client, f'/api/courses/{course_id}/posts/{post_id}'), timeout=5)
    assert get_post.status_code == 200
    assert get_post.json()['post']['title'] == 'Exam review'

    post_search = client.get(api_url(client, f'/api/courses/{course_id}/search/posts?keyword=routing'), timeout=5)
    assert post_search.status_code == 200
    assert any(p['post_id'] == post_id for p in post_search.json()['posts'])

    comment_search = client.get(api_url(client, f'/api/courses/{course_id}/search/comments?keyword=examples'), timeout=5)
    assert comment_search.status_code == 200
    assert any(c['comment_id'] == comment_id for c in comment_search.json()['comments'])

    update_comment = client.put(
        api_url(client, f'/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}'),
        json={'body': 'Updated comment body'},
        timeout=5,
    )
    assert update_comment.status_code == 200
    assert update_comment.json()['comment']['body'] == 'Updated comment body'


def test_assignment_submission_and_upload_flow(client, app_instance):
    teacher = register(client, 'teacher_assign')
    course = create_course(client, 'Software Testing', 'With submissions')
    course_id = course['course_id']

    logout(client)
    student = register(client, 'student_assign')
    logout(client)
    login(client, 'teacher_assign')
    add_member(client, course_id, student['user_id'], 'student')

    assignment_res = client.post(
        api_url(client, f'/api/courses/{course_id}/assignments'),
        json={'title': 'HW1', 'description': 'Implement unit tests', 'due_at': '2030-01-01T00:00:00Z'},
        timeout=5,
    )
    assert assignment_res.status_code == 200, assignment_res.text
    assignment_id = assignment_res.json()['assignment']['assignment_id']

    file_path = Path(app_instance['app_dir']) / 'sample.txt'
    file_path.write_text('hello upload', encoding='utf-8')
    with file_path.open('rb') as fh:
        upload_res = client.post(
            api_url(client, f'/api/courses/{course_id}/uploads'),
            files={'file': ('sample.txt', fh, 'text/plain')},
            timeout=5,
        )
    assert upload_res.status_code == 200, upload_res.text
    upload = upload_res.json()['upload']
    upload_id = upload['upload_id']

    download_res = client.get(api_url(client, f'/api/courses/{course_id}/uploads/{upload_id}/download'), timeout=5)
    assert download_res.status_code == 200
    assert download_res.content == b'hello upload'

    logout(client)
    login(client, 'student_assign')

    submit_res = client.post(
        api_url(client, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions'),
        json={'content_text': 'My answer', 'attachment_upload_id': upload_id},
        timeout=5,
    )
    assert submit_res.status_code == 200, submit_res.text
    submission_id = submit_res.json()['submission']['submission_id']

    mine_res = client.get(api_url(client, '/api/my/submissions'), timeout=5)
    assert mine_res.status_code == 200
    assert any(s['submission_id'] == submission_id for s in mine_res.json()['submissions'])

    update_res = client.put(
        api_url(client, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}'),
        json={'content_text': 'My revised answer', 'attachment_upload_id': upload_id},
        timeout=5,
    )
    assert update_res.status_code == 200, update_res.text
    assert update_res.json()['submission']['content_text'] == 'My revised answer'

    logout(client)
    login(client, 'teacher_assign')
    all_subs = client.get(api_url(client, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions'), timeout=5)
    assert all_subs.status_code == 200
    assert any(s['submission_id'] == submission_id for s in all_subs.json()['submissions'])


def test_question_bank_quiz_and_attempt_flow(client):
    teacher = register(client, 'teacher_quiz')
    course = create_course(client, 'Databases', 'Quiz course')
    course_id = course['course_id']

    logout(client)
    student = register(client, 'student_quiz')
    logout(client)
    login(client, 'teacher_quiz')
    add_member(client, course_id, student['user_id'], 'student')

    q_res = client.post(
        api_url(client, f'/api/courses/{course_id}/questions'),
        json={
            'qtype': 'single_choice',
            'prompt': 'What does SQL stand for?',
            'options_json': '["Structured Query Language","Simple Query Logic"]',
            'answer_json': '"Structured Query Language"',
        },
        timeout=5,
    )
    assert q_res.status_code == 200, q_res.text
    question_id = q_res.json()['question']['question_id']

    quiz_res = client.post(
        api_url(client, f'/api/courses/{course_id}/quizzes'),
        json={
            'title': 'Quiz 1',
            'description': 'Intro quiz',
            'open_at': '2030-01-01T00:00:00Z',
            'due_at': '2030-01-02T00:00:00Z',
        },
        timeout=5,
    )
    assert quiz_res.status_code == 200, quiz_res.text
    quiz_id = quiz_res.json()['quiz']['quiz_id']

    link_res = client.post(
        api_url(client, f'/api/courses/{course_id}/quizzes/{quiz_id}/questions'),
        json={'question_id': question_id, 'points': 5, 'position': 1},
        timeout=5,
    )
    assert link_res.status_code == 200, link_res.text

    quiz_get = client.get(api_url(client, f'/api/courses/{course_id}/quizzes/{quiz_id}'), timeout=5)
    assert quiz_get.status_code == 200
    assert any(q['question_id'] == question_id for q in quiz_get.json()['quiz_questions'])

    logout(client)
    login(client, 'student_quiz')

    start_res = client.post(api_url(client, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start'), timeout=5)
    assert start_res.status_code == 200, start_res.text
    attempt_id = start_res.json()['attempt']['attempt_id']

    ans_res = client.post(
        api_url(client, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers'),
        json={'question_id': question_id, 'answer_json': '"Structured Query Language"'},
        timeout=5,
    )
    assert ans_res.status_code == 200, ans_res.text

    submit_res = client.post(api_url(client, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit'), timeout=5)
    assert submit_res.status_code == 200, submit_res.text

    mine = client.get(api_url(client, '/api/my/quizzes/attempts'), timeout=5)
    assert mine.status_code == 200
    attempts = mine.json()['attempts']
    matched = [a for a in attempts if a['attempt_id'] == attempt_id]
    assert matched
    assert matched[0]['submitted_at'] is not None
