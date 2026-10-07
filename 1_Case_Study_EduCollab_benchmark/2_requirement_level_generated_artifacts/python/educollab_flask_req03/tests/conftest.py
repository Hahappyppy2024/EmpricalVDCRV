import importlib.util
import os
import shutil
import sys
from pathlib import Path

import pytest


@pytest.fixture(scope='session')
def source_app_dir() -> Path:
    return Path(__file__).resolve().parent.parent


@pytest.fixture()
def app_instance(tmp_path, source_app_dir, monkeypatch):
    workdir = tmp_path / 'app_under_test'
    shutil.copytree(source_app_dir, workdir, ignore=shutil.ignore_patterns(
        '.venv', '__pycache__', '.pytest_cache', 'tests', 'data', 'storage'
    ))

    monkeypatch.chdir(workdir)
    sys.path.insert(0, str(workdir))

    module_name = f'app_under_test_{os.getpid()}_{id(workdir)}'
    spec = importlib.util.spec_from_file_location(module_name, workdir / 'app.py')
    module = importlib.util.module_from_spec(spec)
    assert spec and spec.loader
    spec.loader.exec_module(module)

    app = module.create_app()
    app.config.update(TESTING=True)

    yield app

    sys.path = [p for p in sys.path if p != str(workdir)]


@pytest.fixture()
def client(app_instance):
    return app_instance.test_client()


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
    res = client.post('/api/courses', json={'title': title, 'description': description})
    assert res.status_code == 201, res.get_json()
    return res.get_json()['course']


def add_member(client, course_id, user_id, role_in_course='student'):
    return client.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': user_id, 'role_in_course': role_in_course},
    )


def create_post(client, course_id, title='Hello', body='Post body'):
    return client.post(
        f'/api/courses/{course_id}/posts',
        json={'title': title, 'body': body},
    )


def create_comment(client, course_id, post_id, body='Comment body'):
    return client.post(
        f'/api/courses/{course_id}/posts/{post_id}/comments',
        json={'body': body},
    )