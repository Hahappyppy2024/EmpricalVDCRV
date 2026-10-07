from conftest import api_delete, api_put, api_url, create_course, login, logout, me, register


def test_auth_register_login_me_logout_flow(client):
    user = register(client, 'alice')
    assert user['username'] == 'alice'
    assert user['display_name'] == 'Alice'

    me_payload = me(client).json()
    assert me_payload['success'] is True
    assert me_payload['user']['username'] == 'alice'

    logout_payload = logout(client)
    assert logout_payload['success'] is True

    unauth = me(client, expected_status=401).json()
    assert unauth['success'] is False

    login(client, 'alice', 'pass123')
    me_payload_again = me(client).json()
    assert me_payload_again['success'] is True
    assert me_payload_again['user']['username'] == 'alice'


def test_admin_can_create_list_get_update_and_delete_course(client):
    login(client, 'admin', 'admin123')

    created = create_course(client, 'Distributed Systems', 'Spring section')
    course_id = created['course_id']
    assert created['title'] == 'Distributed Systems'
    assert created['description'] == 'Spring section'
    if 'creator_username' in created:
        assert created['creator_username'] == 'admin'

    list_res = client.get(api_url(client, '/api/courses'), timeout=5)
    assert list_res.status_code == 200
    list_payload = list_res.json()
    assert list_payload['success'] is True
    listed = list_payload['courses']
    assert any(c['course_id'] == course_id and c['title'] == 'Distributed Systems' for c in listed)

    get_res = client.get(api_url(client, f'/api/courses/{course_id}'), timeout=5)
    assert get_res.status_code == 200
    get_payload = get_res.json()
    assert get_payload['success'] is True
    got = get_payload['course']
    assert got['course_id'] == course_id
    assert got['description'] == 'Spring section'

    update_res = api_put(
        client,
        f'/api/courses/{course_id}',
        {'title': 'Distributed Systems Updated', 'description': 'Edited'},
    )
    update_payload = update_res.json()
    assert update_payload['success'] is True
    updated = update_payload['course']
    assert updated['title'] == 'Distributed Systems Updated'
    assert updated['description'] == 'Edited'

    delete_res = api_delete(client, f'/api/courses/{course_id}')
    delete_payload = delete_res.json()
    assert delete_payload['success'] is True

    missing = client.get(api_url(client, f'/api/courses/{course_id}'), timeout=5)
    assert missing.status_code == 404
    missing_payload = missing.json()
    assert missing_payload['success'] is False