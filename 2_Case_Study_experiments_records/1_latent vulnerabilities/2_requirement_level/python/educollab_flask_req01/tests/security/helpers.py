def json_of(resp):
    return resp.get_json()


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
    return client.post(
        '/api/auth/login',
        json={
            'username': username,
            'password': password,
        },
    )


def api_logout(client):
    return client.post('/api/auth/logout')


def api_me(client):
    return client.get('/api/auth/me')


def create_course(client, title='Course A', description='Desc'):
    return client.post(
        '/api/courses',
        json={
            'title': title,
            'description': description,
        },
    )


def get_course(client, course_id):
    return client.get(f'/api/courses/{course_id}')


def update_course(client, course_id, title, description):
    return client.put(
        f'/api/courses/{course_id}',
        json={
            'title': title,
            'description': description,
        },
    )


def delete_course(client, course_id):
    return client.delete(f'/api/courses/{course_id}')


def list_users(client):
    return client.get('/api/users')