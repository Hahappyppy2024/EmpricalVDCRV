from conftest import (
    api_login,
    api_logout,
    api_register,
    create_course,
    get_membership_id,
    get_user_id,
)


def test_r2_membership_crud_flow(client, fresh_client, db_path):
    # prerequisite setup from earlier requirements
    login_res = api_login(client, 'admin', 'admin123')
    assert login_res.status_code == 200

    course_res = create_course(client, 'R2 Course', 'membership flow')
    assert course_res.status_code == 201
    course_id = course_res.get_json()['course']['course_id']

    student_client = fresh_client()
    register_res = api_register(student_client, 'student_r2', 'pass123', 'Student R2')
    assert register_res.status_code == 201
    api_logout(student_client)

    student_user_id = get_user_id(db_path, 'student_r2')
    assert student_user_id is not None

    # r2 focus: add course member
    add_res = client.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_user_id, 'role_in_course': 'student'},
    )
    assert add_res.status_code == 201
    added = add_res.get_json()['membership']
    assert added['course_id'] == course_id
    assert added['user_id'] == student_user_id
    assert added['role_in_course'] == 'student'

    # r2 focus: list members
    list_res = client.get(f'/api/courses/{course_id}/members')
    assert list_res.status_code == 200
    members = list_res.get_json()['members']
    usernames = {m['username']: m['role_in_course'] for m in members}
    assert usernames['admin'] == 'admin'
    assert usernames['student_r2'] == 'student'

    membership_id = get_membership_id(db_path, course_id, 'student_r2')
    assert membership_id is not None

    # r2 focus: update member role
    update_res = client.put(
        f'/api/courses/{course_id}/members/{membership_id}',
        json={'role_in_course': 'assistant'},
    )
    assert update_res.status_code == 200
    assert update_res.get_json()['membership']['role_in_course'] == 'assistant'

    # student sees joined course in my memberships
    api_login(student_client, 'student_r2', 'pass123')
    my_res = student_client.get('/api/memberships')
    assert my_res.status_code == 200
    memberships = my_res.get_json()['memberships']
    assert len(memberships) == 1
    assert memberships[0]['course_id'] == course_id
    assert memberships[0]['course_title'] == 'R2 Course'
    assert memberships[0]['role_in_course'] == 'assistant'

    # r2 focus: remove member
    remove_res = client.delete(f'/api/courses/{course_id}/members/{membership_id}')
    assert remove_res.status_code == 200

    my_after_res = student_client.get('/api/memberships')
    assert my_after_res.status_code == 200
    assert my_after_res.get_json()['memberships'] == []


def test_r2_membership_endpoints_require_teacher_or_admin(client, fresh_client, db_path):
    api_login(client, 'admin', 'admin123')
    course_id = create_course(client, 'Protected R2 Course', 'membership auth').get_json()['course']['course_id']

    student_client = fresh_client()
    api_register(student_client, 'student_guard', 'pass123', 'Student Guard')
    api_logout(student_client)
    student_user_id = get_user_id(db_path, 'student_guard')

    # Admin adds the student normally.
    add_res = client.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_user_id, 'role_in_course': 'student'},
    )
    assert add_res.status_code == 201
    membership_id = add_res.get_json()['membership']['membership_id']

    api_login(student_client, 'student_guard', 'pass123')

    forbidden_add = student_client.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_user_id, 'role_in_course': 'assistant'},
    )
    assert forbidden_add.status_code == 403
    assert forbidden_add.get_json()['error'] == 'teacher_or_admin_required'

    forbidden_update = student_client.put(
        f'/api/courses/{course_id}/members/{membership_id}',
        json={'role_in_course': 'assistant'},
    )
    assert forbidden_update.status_code == 403

    forbidden_delete = student_client.delete(f'/api/courses/{course_id}/members/{membership_id}')
    assert forbidden_delete.status_code == 403
