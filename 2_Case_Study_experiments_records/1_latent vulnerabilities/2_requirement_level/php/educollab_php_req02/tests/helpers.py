
import requests


def api_url(app_instance, path: str) -> str:
    return app_instance['base_url'] + path


def make_client() -> requests.Session:
    s = requests.Session()
    s.headers.update({'Accept': 'application/json'})
    return s


def register(client: requests.Session, app_instance, username: str, password: str = 'pass123', display_name: str | None = None):
    payload = {
        'username': username,
        'password': password,
        'display_name': display_name or username,
    }
    res = client.post(api_url(app_instance, '/api/auth/register'), json=payload, timeout=5)
    assert res.status_code == 201, res.text
    return res.json()['user']


def login(client: requests.Session, app_instance, username: str, password: str = 'pass123'):
    res = client.post(api_url(app_instance, '/api/auth/login'), json={'username': username, 'password': password}, timeout=5)
    assert res.status_code == 200, res.text
    return res.json()['user']


def logout(client: requests.Session, app_instance):
    res = client.post(api_url(app_instance, '/api/auth/logout'), timeout=5)
    assert res.status_code == 200, res.text


def create_course(client: requests.Session, app_instance, title='Course A', description='Desc'):
    res = client.post(api_url(app_instance, '/api/courses'), json={'title': title, 'description': description}, timeout=5)
    assert res.status_code == 201, res.text
    return res.json()['course']
