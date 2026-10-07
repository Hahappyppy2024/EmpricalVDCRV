import importlib.util
import shutil
import sys
from pathlib import Path

import pytest


PROJECT_CANDIDATES = [
    Path(__file__).resolve().parent.parent,  # if tests are copied into project root/tests
    Path.cwd(),
]


def _find_project_root() -> Path:
    for base in PROJECT_CANDIDATES:
        if (base / 'app.py').exists() and (base / 'controllers').exists():
            return base
    raise RuntimeError('Could not find project root containing app.py and controllers/.')


def load_create_app(project_dir: Path):
    sys.path.insert(0, str(project_dir))
    spec = importlib.util.spec_from_file_location('educollab_app_module', project_dir / 'app.py')
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module.create_app


@pytest.fixture()
def client(tmp_path):
    source_root = _find_project_root()
    app_dir = tmp_path / 'app_under_test'
    shutil.copytree(source_root, app_dir, dirs_exist_ok=True)

    db_path = app_dir / 'data' / 'app.db'
    if db_path.exists():
        db_path.unlink()

    create_app = load_create_app(app_dir)
    app = create_app()
    app.config.update(TESTING=True)

    with app.test_client() as client:
        yield client
