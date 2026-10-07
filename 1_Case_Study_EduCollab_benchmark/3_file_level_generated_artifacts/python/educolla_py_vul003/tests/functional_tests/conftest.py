import shutil
import sys
from pathlib import Path

import pytest

PROJECT_ROOT = Path(__file__).resolve().parents[2]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

import app as app_module
from db import DB_PATH


UPLOAD_ROOT = PROJECT_ROOT / "data" / "uploads"


def reset_test_state():
    db_path = Path(DB_PATH)
    if db_path.exists():
        db_path.unlink()

    if UPLOAD_ROOT.exists():
        shutil.rmtree(UPLOAD_ROOT, ignore_errors=True)
    UPLOAD_ROOT.mkdir(parents=True, exist_ok=True)


@pytest.fixture
def app_instance():
    reset_test_state()

    app_module.app.config.update(
        TESTING=True,
        SECRET_KEY="test-secret-key",
    )

    # let the application initialize its own database/state
    with app_module.app.test_client() as c:
        c.get("/", follow_redirects=False)

    yield app_module.app


@pytest.fixture
def client(app_instance):
    with app_instance.test_client() as client:
        yield client