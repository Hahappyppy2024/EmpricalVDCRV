from helpers import api_url, create_course, login, logout, make_client, register


def test_r2_membership_add_list_update_remove_and_my_memberships(app_instance):
    teacher = make_client()
    student = make_client()

    # setup accounts / course
    login(teacher, app_instance, 'admin', 'admin123')
    course = create_course(teacher, app_instance, 'R2 Membership Course', 'membership feature')
    course_id = course['course_id']

    logout(teacher, app_instance)
    register(teacher, app_instance, 'teacher_r2')
    teacher_course = create_course(teacher, app_instance, 'Teacher Owned Course', 'r2 setup')
    teacher_course_id = teacher_course['course_id']

    register(student, app_instance, 'student_r2')
    student_user_id = student.get(api_url(app_instance, '/api/auth/me'), timeout=5).json()['user']['user_id']

    # add student into teacher-owned course
    add_res = teacher.post(
        api_url(app_instance, f'/api/courses/{teacher_course_id}/members'),
        json={'user_id': student_user_id, 'role_in_course': 'student'},
        timeout=5,
    )
    assert add_res.status_code == 201, add_res.text
    add_body = add_res.json()
    assert add_body['success'] is True
    membership_id = add_body['membership']['membership_id']
    assert add_body['membership']['role_in_course'] == 'student'
    assert add_body['membership']['user_id'] == student_user_id

    # teacher can list members of the course
    list_res = teacher.get(api_url(app_instance, f'/api/courses/{teacher_course_id}/members'), timeout=5)
    assert list_res.status_code == 200, list_res.text
    members = list_res.json()['members']
    assert len(members) == 2
    assert any(m['username'] == 'teacher_r2' and m['role_in_course'] == 'teacher' for m in members)
    assert any(m['username'] == 'student_r2' and m['role_in_course'] == 'student' for m in members)

    # course member can view course members
    list_as_student = student.get(api_url(app_instance, f'/api/courses/{teacher_course_id}/members'), timeout=5)
    assert list_as_student.status_code == 200, list_as_student.text
    assert any(m['username'] == 'student_r2' for m in list_as_student.json()['members'])

    # teacher can update member role
    update_res = teacher.put(
        api_url(app_instance, f'/api/courses/{teacher_course_id}/members/{membership_id}'),
        json={'role_in_course': 'assistant'},
        timeout=5,
    )
    assert update_res.status_code == 200, update_res.text
    assert update_res.json()['membership']['role_in_course'] == 'assistant'

    # member can view own memberships
    my_res = student.get(api_url(app_instance, '/api/memberships'), timeout=5)
    assert my_res.status_code == 200, my_res.text
    memberships = my_res.json()['memberships']
    assert any(m['course_id'] == teacher_course_id and m['role_in_course'] == 'assistant' for m in memberships)

    # teacher can remove member
    delete_res = teacher.delete(api_url(app_instance, f'/api/courses/{teacher_course_id}/members/{membership_id}'), timeout=5)
    assert delete_res.status_code == 200, delete_res.text
    assert delete_res.json()['success'] is True

    after_delete = teacher.get(api_url(app_instance, f'/api/courses/{teacher_course_id}/members'), timeout=5)
    remaining = after_delete.json()['members']
    assert len(remaining) == 1
    assert remaining[0]['username'] == 'teacher_r2'


def test_r2_membership_authorization_and_validation(app_instance):
    teacher = make_client()
    outsider = make_client()

    register(teacher, app_instance, 'teacher_auth')
    course = create_course(teacher, app_instance, 'Course Auth', 'r2')
    course_id = course['course_id']

    register(outsider, app_instance, 'outsider_auth')
    outsider_user_id = outsider.get(api_url(app_instance, '/api/auth/me'), timeout=5).json()['user']['user_id']

    # outsider is not a course member -> cannot list members
    forbid_list = outsider.get(api_url(app_instance, f'/api/courses/{course_id}/members'), timeout=5)
    assert forbid_list.status_code == 403, forbid_list.text

    # teacher can add outsider once
    add_once = teacher.post(
        api_url(app_instance, f'/api/courses/{course_id}/members'),
        json={'user_id': outsider_user_id, 'role_in_course': 'student'},
        timeout=5,
    )
    assert add_once.status_code == 201, add_once.text
    membership_id = add_once.json()['membership']['membership_id']

    # duplicate add is rejected
    add_twice = teacher.post(
        api_url(app_instance, f'/api/courses/{course_id}/members'),
        json={'user_id': outsider_user_id, 'role_in_course': 'student'},
        timeout=5,
    )
    assert add_twice.status_code == 422, add_twice.text

    # invalid role is rejected
    bad_role = teacher.put(
        api_url(app_instance, f'/api/courses/{course_id}/members/{membership_id}'),
        json={'role_in_course': 'superuser'},
        timeout=5,
    )
    assert bad_role.status_code == 422, bad_role.text

    # non-teacher member cannot manage memberships
    promote_attempt = outsider.put(
        api_url(app_instance, f'/api/courses/{course_id}/members/{membership_id}'),
        json={'role_in_course': 'assistant'},
        timeout=5,
    )
    assert promote_attempt.status_code == 403, promote_attempt.text
