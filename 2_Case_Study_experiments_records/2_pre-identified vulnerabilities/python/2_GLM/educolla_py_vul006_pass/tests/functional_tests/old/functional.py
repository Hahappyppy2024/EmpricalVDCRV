from io import BytesIO

from conftest import api_login, api_register


def get_user_id_by_username(client, username):
    users = client.get('/api/users').get_json()['users']
    for user in users:
        if user['username'] == username:
            return user['user_id']
    raise AssertionError(f'user not found: {username}')


def test_auth_register_login_me_logout_flow(client):
    res = api_register(client, 'student_a', 'pass123', 'Student A')
    assert res.status_code == 200
    body = res.get_json()
    assert body['username'] == 'student_a'

    me = client.get('/api/auth/me')
    assert me.status_code == 200
    assert me.get_json()['user']['display_name'] == 'Student A'

    logout = client.post('/api/auth/logout')
    assert logout.status_code == 200
    assert logout.get_json()['ok'] is True

    me_after = client.get('/api/auth/me')
    assert me_after.status_code == 401

    login = api_login(client, 'student_a', 'pass123')
    assert login.status_code == 200
    assert login.get_json()['user']['username'] == 'student_a'


def test_course_membership_and_post_comment_flow(client):
    teacher = client
    assert api_login(teacher, 'admin', 'admin123').status_code == 200

    course_res = teacher.post('/api/courses', json={'title': 'CS101', 'description': 'Intro'})
    assert course_res.status_code == 200
    course_id = course_res.get_json()['course_id']

    student = teacher.application.test_client()
    api_register(student, 'student_b', 'pass123', 'Student B')

    student_user_id = get_user_id_by_username(teacher, 'student_b')
    add_member = teacher.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_user_id, 'role_in_course': 'student'},
    )
    assert add_member.status_code == 200
    membership_id = add_member.get_json()['membership_id']

    members = teacher.get(f'/api/courses/{course_id}/members')
    assert members.status_code == 200
    member_roles = {m['role_in_course'] for m in members.get_json()['members']}
    assert 'teacher' in member_roles
    assert 'student' in member_roles

    post_res = student.post(
        f'/api/courses/{course_id}/posts',
        json={'title': 'Question 1', 'body': 'When is the deadline?'},
    )
    assert post_res.status_code == 200
    post_id = post_res.get_json()['post_id']

    comment_res = teacher.post(
        f'/api/courses/{course_id}/posts/{post_id}/comments',
        json={'body': 'Friday.'},
    )
    assert comment_res.status_code == 200
    comment_id = comment_res.get_json()['comment_id']
    assert comment_id > 0

    detail = student.get(f'/api/courses/{course_id}/posts/{post_id}')
    assert detail.status_code == 200
    payload = detail.get_json()
    assert payload['post']['title'] == 'Question 1'
    assert len(payload['comments']) == 1
    assert payload['comments'][0]['body'] == 'Friday.'

    update_member = teacher.put(
        f'/api/courses/{course_id}/members/{membership_id}',
        json={'role_in_course': 'assistant'},
    )
    assert update_member.status_code == 200
    updated_members = teacher.get(f'/api/courses/{course_id}/members').get_json()['members']
    assert any(m['membership_id'] == membership_id and m['role_in_course'] == 'assistant' for m in updated_members)


def test_assignment_submission_flow(client):
    teacher = client
    api_login(teacher, 'admin', 'admin123')
    course_id = teacher.post('/api/courses', json={'title': 'SE', 'description': 'Software'}).get_json()['course_id']

    student = teacher.application.test_client()
    api_register(student, 'student_c')
    student_user_id = get_user_id_by_username(teacher, 'student_c')
    teacher.post(f'/api/courses/{course_id}/members', json={'user_id': student_user_id, 'role_in_course': 'student'})

    create_assignment = teacher.post(
        f'/api/courses/{course_id}/assignments',
        json={'title': 'HW1', 'description': 'First homework', 'due_at': '2026-03-10T23:59:00'},
    )
    assert create_assignment.status_code == 200
    assignment_id = create_assignment.get_json()['assignment_id']

    create_submission = student.post(
        f'/api/courses/{course_id}/assignments/{assignment_id}/submissions',
        json={'content_text': 'My answer', 'attachment_upload_id': None},
    )
    assert create_submission.status_code == 200
    submission_id = create_submission.get_json()['submission_id']

    my_submissions = student.get('/api/my/submissions')
    assert my_submissions.status_code == 200
    subs = my_submissions.get_json()['submissions']
    assert any(s['submission_id'] == submission_id and s['assignment_title'] == 'HW1' for s in subs)

    update_submission = student.put(
        f'/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}',
        json={'content_text': 'Updated answer', 'attachment_upload_id': None},
    )
    assert update_submission.status_code == 200

    teacher_view = teacher.get(f'/api/courses/{course_id}/assignments/{assignment_id}/submissions')
    assert teacher_view.status_code == 200
    teacher_subs = teacher_view.get_json()['submissions']
    assert any(s['submission_id'] == submission_id and s['content_text'] == 'Updated answer' for s in teacher_subs)


def test_upload_create_list_download_delete_flow(client):
    api_login(client, 'admin', 'admin123')
    course_id = client.post('/api/courses', json={'title': 'Net', 'description': 'Networks'}).get_json()['course_id']

    upload_res = client.post(
        f'/api/courses/{course_id}/uploads',
        data={'file': (BytesIO(b'hello upload'), 'hello.txt')},
        content_type='multipart/form-data',
    )
    assert upload_res.status_code == 200
    upload_id = upload_res.get_json()['upload_id']

    listing = client.get(f'/api/courses/{course_id}/uploads')
    assert listing.status_code == 200
    uploads = listing.get_json()['uploads']
    assert any(u['upload_id'] == upload_id and u['original_name'] == 'hello.txt' for u in uploads)

    download = client.get(f'/api/courses/{course_id}/uploads/{upload_id}/download')
    assert download.status_code == 200
    assert download.data == b'hello upload'

    delete_res = client.delete(f'/api/courses/{course_id}/uploads/{upload_id}')
    assert delete_res.status_code == 200
    uploads_after = client.get(f'/api/courses/{course_id}/uploads').get_json()['uploads']
    assert all(u['upload_id'] != upload_id for u in uploads_after)


def test_question_bank_quiz_attempt_flow(client):
    teacher = client
    api_login(teacher, 'admin', 'admin123')
    course_id = teacher.post(
        '/api/courses',
        json={'title': 'Quiz Course', 'description': 'Q'}
    ).get_json()['course_id']

    student = teacher.application.test_client()
    reg_res = api_register(student, 'student_d')
    assert reg_res.status_code == 200

    student_user_id = get_user_id_by_username(teacher, 'student_d')

    add_member_res = teacher.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_user_id, 'role_in_course': 'student'}
    )
    assert add_member_res.status_code == 200

    question_res = teacher.post(
        f'/api/courses/{course_id}/questions',
        json={
            'qtype': 'single_choice',
            'prompt': '2+2=?',
            'options_json': ['3', '4', '5'],
            'answer_json': {'correct': '4'},
        },
    )
    assert question_res.status_code == 200
    question_id = question_res.get_json()['question_id']

    quiz_res = teacher.post(
        f'/api/courses/{course_id}/quizzes',
        json={'title': 'Quiz 1', 'description': 'Basic quiz', 'open_at': '', 'due_at': ''},
    )
    assert quiz_res.status_code == 200
    quiz_id = quiz_res.get_json()['quiz_id']

    add_q = teacher.post(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/questions',
        json={'question_id': question_id, 'points': 5, 'position': 1},
    )
    assert add_q.status_code == 200

    quiz_detail = teacher.get(f'/api/courses/{course_id}/quizzes/{quiz_id}')
    assert quiz_detail.status_code == 200
    detail_json = quiz_detail.get_json()
    assert detail_json['quiz']['title'] == 'Quiz 1'
    assert len(detail_json['quiz_questions']) == 1
    assert detail_json['quiz_questions'][0]['question_id'] == question_id

    start_attempt = student.post(f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start')
    assert start_attempt.status_code == 200
    attempt_id = start_attempt.get_json()['attempt_id']

    answer_res = student.post(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers',
        json={'question_id': question_id, 'answer_json': {'selected': '4'}},
    )
    assert answer_res.status_code == 200
    assert answer_res.get_json()['ok'] is True

    submit_res = student.post(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit'
    )
    assert submit_res.status_code == 200
    assert submit_res.get_json()['ok'] is True