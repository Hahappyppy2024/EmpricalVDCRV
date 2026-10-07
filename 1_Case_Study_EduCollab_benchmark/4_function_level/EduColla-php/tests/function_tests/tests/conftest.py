import os
import pytest
import os

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8000").rstrip("/")

@pytest.fixture(scope="session")
def base_url():
    return os.environ.get("BASE_URL", "http://localhost:8000").rstrip("/")
