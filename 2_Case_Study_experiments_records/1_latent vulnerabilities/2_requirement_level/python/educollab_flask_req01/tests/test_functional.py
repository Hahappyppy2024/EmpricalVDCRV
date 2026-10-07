def api_register(client, username, password='pass123', display_name=None):
    return client.post(
        '/api/auth/register',
        json={
            'username': username,
            'password': password,
            'display_name': display_name or username,
        },
    )


def api_login(client, username, password='pass123'):
    return client.post('/api/auth/login', json={'username': username, 'password': password})


def api_logout(client):
    return client.post('/api/auth/logout')


def create_course(client, title='Course A', description='Desc'):
    return client.post('/api/courses', json={'title': title, 'description': description})


# Functional: auth lifecycle works through the JSON API.
def test_auth_register_login_me_logout_flow(client):
    register_res = api_register(client, 'alice', 'pw123', 'Alice')
    assert register_res.status_code == 201
    body = register_res.get_json()
    assert body['success'] is True
    assert body['user']['username'] == 'alice'
    assert body['user']['display_name'] == 'Alice'

    me_res = client.get('/api/auth/me')
    assert me_res.status_code == 200
    assert me_res.get_json()['user']['username'] == 'alice'

    logout_res = api_logout(client)
    assert logout_res.status_code == 200
    assert logout_res.get_json()['success'] is True

    me_after_logout = client.get('/api/auth/me')
    assert me_after_logout.status_code == 401
    assert me_after_logout.get_json()['error'] == 'authentication_required'

    login_res = api_login(client, 'alice', 'pw123')
    assert login_res.status_code == 200
    assert login_res.get_json()['user']['username'] == 'alice'


# Functional: course CRUD works for a logged-in user.
def test_course_create_list_get_update_delete_flow(client):
    login_admin = api_login(client, 'admin', 'admin123')
    assert login_admin.status_code == 200

    create_res = create_course(client, 'Distributed Systems', 'Spring term')
    assert create_res.status_code == 201
    created = create_res.get_json()['course']
    course_id = created['course_id']
    assert created['title'] == 'Distributed Systems'
    assert created['description'] == 'Spring term'
    assert created['created_by'] > 0

    list_res = client.get('/api/courses')
    assert list_res.status_code == 200
    courses = list_res.get_json()['courses']
    assert any(c['course_id'] == course_id and c['title'] == 'Distributed Systems' for c in courses)

    get_res = client.get(f'/api/courses/{course_id}')
    assert get_res.status_code == 200
    assert get_res.get_json()['course']['description'] == 'Spring term'

    update_res = client.put(
        f'/api/courses/{course_id}',
        json={'title': 'Distributed Systems Advanced', 'description': 'Updated desc'},
    )
    assert update_res.status_code == 200
    updated = update_res.get_json()['course']
    assert updated['title'] == 'Distributed Systems Advanced'
    assert updated['description'] == 'Updated desc'

    delete_res = client.delete(f'/api/courses/{course_id}')
    assert delete_res.status_code == 200
    assert delete_res.get_json()['deleted_course_id'] == course_id

    get_after_delete = client.get(f'/api/courses/{course_id}')
    assert get_after_delete.status_code == 404


# Functional: anonymous users can read public course data, but cannot create courses.
def test_public_read_but_auth_required_for_create(client):
    unauth_create = create_course(client, 'Unauthorized Course', 'Should fail')
    assert unauth_create.status_code == 401
    assert unauth_create.get_json()['error'] == 'authentication_required'

    api_login(client, 'admin', 'admin123')
    create_res = create_course(client, 'Public Course', 'Visible to everyone')
    course_id = create_res.get_json()['course']['course_id']
    api_logout(client)

    list_res = client.get('/api/courses')
    assert list_res.status_code == 200
    assert any(c['course_id'] == course_id for c in list_res.get_json()['courses'])

    get_res = client.get(f'/api/courses/{course_id}')
    assert get_res.status_code == 200
    assert get_res.get_json()['course']['title'] == 'Public Course'
