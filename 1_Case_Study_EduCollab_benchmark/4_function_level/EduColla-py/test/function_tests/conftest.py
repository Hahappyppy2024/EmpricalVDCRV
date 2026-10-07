import os
import sys
import pytest

# ensure project root is importable
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from app import create_app
from config import TestConfig
from database import init_db, seed_admin, get_db_connection
from models.course import CourseRepository

collect_ignore_glob = ["old/*", "old_backup/*"]

@pytest.fixture
def app(tmp_path):
    db_path = tmp_path / "test.db"
    upload_path = tmp_path / "uploads"
    upload_path.mkdir(parents=True, exist_ok=True)

    class LocalTestConfig(TestConfig):
        DATABASE_PATH = str(db_path)
        UPLOAD_FOLDER = str(upload_path)
        TESTING = True

    app = create_app(LocalTestConfig)
    with app.app_context():
        init_db()
        seed_admin()
    return app


@pytest.fixture
def client(app):
    return app.test_client()


@pytest.fixture
def auth(client):
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
            return client.post("/logout", follow_redirects=True)

        def api_login(self, username, password):
            return client.post("/api/auth/login", json={"username": username, "password": password})

        def api_logout(self):
            return client.post("/api/auth/logout")

    return AuthActions()


@pytest.fixture
def admin_logged_in(client, auth):
    # seeded by seed_admin(): admin / admin123
    rv = auth.login("admin", "admin123")
    assert rv.status_code in (200, 302)
    return True


@pytest.fixture
def course_with_admin(app, client, admin_logged_in):
    # create a course as the admin (via repo for determinism)
    with app.app_context():
        conn = get_db_connection()
        admin = conn.execute("SELECT user_id FROM user WHERE username='admin'").fetchone()
        assert admin is not None
        course_id = CourseRepository.create("Test Course", "Desc", admin["user_id"])
        conn.close()
        return course_id


@pytest.fixture
def student_user(app, auth):
    # create a normal user via HTTP so password hash is correct
    rv = auth.register("student", "pass", "Student User")
    assert rv.status_code in (200, 302)
    with app.app_context():
        conn = get_db_connection()
        u = conn.execute("SELECT user_id FROM user WHERE username='student'").fetchone()
        conn.close()
        assert u is not None
        return u["user_id"]


def add_membership(app, course_id, user_id, role):
    with app.app_context():
        conn = get_db_connection()
        conn.execute(
            "INSERT OR REPLACE INTO membership (course_id, user_id, role_in_course) VALUES (?, ?, ?)",
            (course_id, user_id, role),
        )
        conn.commit()
        conn.close()
