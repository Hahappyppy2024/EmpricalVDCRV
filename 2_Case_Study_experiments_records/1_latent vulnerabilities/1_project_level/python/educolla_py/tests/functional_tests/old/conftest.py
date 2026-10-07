import importlib.util
import os
import shutil
import sys
from pathlib import Path

import pytest


@pytest.fixture()
def app_instance(tmp_path):
    """Copy the whole project into a temp directory and import a fresh Flask app."""
    source_root = Path(__file__).resolve().parents[2]
    app_root = tmp_path / 'educolla_py'
    shutil.copytree(source_root, app_root)

    data_dir = app_root / 'data'
    data_dir.mkdir(exist_ok=True)

    old_cwd = os.getcwd()
    os.chdir(app_root)
    sys.path.insert(0, str(app_root))

    module_name = f"educollab_test_app_{tmp_path.name}"
    spec = importlib.util.spec_from_file_location(module_name, app_root / 'app.py')
    module = importlib.util.module_from_spec(spec)
    assert spec and spec.loader
    spec.loader.exec_module(module)

    module.app.config.update(TESTING=True)

    try:
        yield module.app, app_root
    finally:
        sys.path = [p for p in sys.path if p != str(app_root)]
        sys.modules.pop(module_name, None)
        os.chdir(old_cwd)


@pytest.fixture()
def client(app_instance):
    app, _ = app_instance
    return app.test_client()


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