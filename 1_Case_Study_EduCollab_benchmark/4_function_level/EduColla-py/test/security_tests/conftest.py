# test/security_tests/conftest.py

import os
import sys
import gc
import pytest

# Ensure project root is on sys.path so imports work when running pytest from repo root
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app import create_app
from config import TestConfig
from database import init_db, seed_admin, get_db_connection


@pytest.fixture
def app(tmp_path):
    """
    Create a Flask app configured for tests with an isolated SQLite database.

    Key point (Windows-safe):
    - Never delete/overwrite a shared fixed DB file (e.g., lms2.db).
    - Use a per-test temporary database file instead to avoid WinError 32 locks.
    """
    db_path = tmp_path / "test.db"

    app = create_app(TestConfig)

    # Force test config + isolated DB path
    app.config["TESTING"] = True
    app.config["DATABASE_PATH"] = str(db_path)

    # Optional: isolate uploads folder too (if your app uses it)
    if "UPLOAD_FOLDER" in app.config:
        app.config["UPLOAD_FOLDER"] = str(tmp_path / "uploads")

    with app.app_context():
        init_db()
        seed_admin()

    yield app

    # Cleanup
    gc.collect()


@pytest.fixture
def client(app):
    return app.test_client()


@pytest.fixture
def auth(client):
    """Auth helper actions used in tests."""

    class AuthActions:
        def register(self, username, password, display_name):
            return client.post(
                "/register",
                data={"username": username, "password": password, "display_name": display_name},
                follow_redirects=True,
            )

        def login(self, username, password):
            return client.post(
                "/login",
                data={"username": username, "password": password},
                follow_redirects=True,
            )

        def logout(self):
            # Your project may implement POST /logout
            return client.post("/logout", follow_redirects=True)

    return AuthActions()


@pytest.fixture
def sample_course(client, auth):
    """Create a course and return its course_id."""
    auth.register("teacher", "password", "Teacher User")
    auth.login("teacher", "password")

    rv = client.post(
        "/courses/new",
        data={"title": "Test Course", "description": "A course for testing"},
        follow_redirects=True,
    )
    assert rv.status_code in (200, 302), "Failed to create course via /courses/new"

    # Read created course_id from DB
    with client.application.app_context():
        course = (
            get_db_connection()
            .execute("SELECT course_id FROM course WHERE title = ? ORDER BY course_id DESC", ("Test Course",))
            .fetchone()
        )
        assert course is not None, "Course not found in DB after creation"
        return course["course_id"]