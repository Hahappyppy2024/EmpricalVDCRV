import io

from helpers import (
    add_member,
    api_url,
    create_assignment,
    create_course,
    get_user_id,
    json_of,
    login,
    register,
    upload_file,
)


def test_r4_assignment_submission_upload_flow(app_instance):
    app = app_instance
    admin = app['session']

    # Precondition setup from older requirements only; not the target under test.
    assert login(admin, app, 'admin', 'admin123').status_code == 200
    course_res = create_course(admin, app, title='R4 Course', description='Assignments/uploads')
    assert course_res.status_code == 201
    course_id = json_of(course_res)['course']['course_id']

    # Add a student for R4 features.
    student_sess = __import__('requests').Session()
    assert register(student_sess, app, 'student_r4').status_code == 201
    student_id = get_user_id(app, 'student_r4')
    add_res = add_member(admin, app, course_id, student_id, 'student')
    assert add_res.status_code == 201

    # Staff creates assignment.
    asg_res = create_assignment(admin, app, course_id, title='HW R4', description='Submit answer')
    assert asg_res.status_code == 201
    asg = json_of(asg_res)['assignment']
    assignment_id = asg['assignment_id']
    assert asg['title'] == 'HW R4'

    # Member can list/get assignment.
    assert login(student_sess, app, 'student_r4', 'pass123').status_code == 200
    list_res = student_sess.get(api_url(app, f'/api/courses/{course_id}/assignments'), timeout=5)
    assert list_res.status_code == 200
    assert any(a['assignment_id'] == assignment_id for a in json_of(list_res)['assignments'])

    get_res = student_sess.get(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}'), timeout=5)
    assert get_res.status_code == 200
    assert json_of(get_res)['assignment']['title'] == 'HW R4'

    # Staff uploads a file for the course.
    up_res = upload_file(admin, app, course_id, filename='starter.txt', content=b'starter-material')
    assert up_res.status_code == 201
    upload_id = json_of(up_res)['upload']['upload_id']

    uploads_res = student_sess.get(api_url(app, f'/api/courses/{course_id}/uploads'), timeout=5)
    assert uploads_res.status_code == 200
    assert any(u['upload_id'] == upload_id for u in json_of(uploads_res)['uploads'])

    dl_res = student_sess.get(api_url(app, f'/api/courses/{course_id}/uploads/{upload_id}/download'), timeout=5)
    assert dl_res.status_code == 200
    assert dl_res.content == b'starter-material'

    # Student submits assignment with content only.
    sub_create = student_sess.post(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions'), json={
        'content_text': 'my answer',
    }, timeout=5)
    assert sub_create.status_code == 201
    submission = json_of(sub_create)['submission']
    submission_id = submission['submission_id']
    assert submission['student_id'] == student_id
    assert submission['attachment_upload_id'] is None

    # Student can see own submissions.
    my_res = student_sess.get(api_url(app, '/api/my/submissions'), timeout=5)
    assert my_res.status_code == 200
    assert any(s['submission_id'] == submission_id for s in json_of(my_res)['submissions'])

    # Student can update own submission.
    sub_upd = student_sess.put(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}'), json={
        'content_text': 'updated answer',
    }, timeout=5)
    assert sub_upd.status_code == 200
    assert json_of(sub_upd)['submission']['content_text'] == 'updated answer'

    # Staff can list submissions for the assignment.
    list_sub = admin.get(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}/submissions'), timeout=5)
    assert list_sub.status_code == 200
    assert any(s['submission_id'] == submission_id for s in json_of(list_sub)['submissions'])

    # Staff can update and delete the assignment.
    upd_asg = admin.put(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}'), json={
        'title': 'HW R4 updated',
        'description': 'Submit updated',
        'due_at': '2031-01-01T00:00:00Z',
    }, timeout=5)
    assert upd_asg.status_code == 200
    assert json_of(upd_asg)['assignment']['title'] == 'HW R4 updated'

    del_upload = admin.delete(api_url(app, f'/api/courses/{course_id}/uploads/{upload_id}'), timeout=5)
    assert del_upload.status_code == 200

    del_asg = admin.delete(api_url(app, f'/api/courses/{course_id}/assignments/{assignment_id}'), timeout=5)
    assert del_asg.status_code == 200


def test_r4_upload_endpoint_rejects_disallowed_types(app_instance):
    app = app_instance
    admin = app['session']

    assert login(admin, app, 'admin', 'admin123').status_code == 200
    course_res = create_course(admin, app, title='R4 Reject Upload', description='Upload restrictions')
    assert course_res.status_code == 201
    course_id = json_of(course_res)['course']['course_id']

    files = {'file': ('shell.php', io.BytesIO(b'<?php echo 1; ?>'), 'application/x-php')}
    res = admin.post(api_url(app, f'/api/courses/{course_id}/uploads'), files=files, timeout=5)
    assert res.status_code == 422
    assert 'not allowed' in (json_of(res).get('error') or '').lower()