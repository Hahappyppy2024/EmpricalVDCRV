
import sqlite3
from urllib.parse import urljoin


def api_url(base_url: str, path: str) -> str:
    return urljoin(base_url.rstrip("/") + "/", path.lstrip("/"))


def register(session, base_url, username, password="pass123", display_name=None):
    payload = {
        "username": username,
        "password": password,
        "display_name": display_name or username,
    }
    return session.post(api_url(base_url, "/api/auth/register"), json=payload, timeout=5)


def login(session, base_url, username, password):
    return session.post(
        api_url(base_url, "/api/auth/login"),
        json={"username": username, "password": password},
        timeout=5,
    )


def logout(session, base_url):
    return session.post(api_url(base_url, "/api/auth/logout"), timeout=5)


def create_course(session, base_url, title, description="desc"):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=5,
    )


def add_member(session, base_url, course_id, user_id, role_in_course):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={"user_id": user_id, "role_in_course": role_in_course},
        timeout=5,
    )


def create_post(session, base_url, course_id, title, body):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/posts"),
        json={"title": title, "body": body},
        timeout=5,
    )


def update_post(session, base_url, course_id, post_id, title, body):
    return session.put(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"),
        json={"title": title, "body": body},
        timeout=5,
    )


def delete_post(session, base_url, course_id, post_id):
    return session.delete(api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"), timeout=5)


def create_comment(session, base_url, course_id, post_id, body):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments"),
        json={"body": body},
        timeout=5,
    )


def update_comment(session, base_url, course_id, post_id, comment_id, body):
    return session.put(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
        json={"body": body},
        timeout=5,
    )


def delete_comment(session, base_url, course_id, post_id, comment_id):
    return session.delete(
        api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
        timeout=5,
    )


def create_question(session, base_url, course_id, qtype, prompt, options_json="", answer_json=""):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/questions"),
        json={
            "qtype": qtype,
            "prompt": prompt,
            "options_json": options_json,
            "answer_json": answer_json,
        },
        timeout=5,
    )


def list_questions(session, base_url, course_id):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/questions"), timeout=5)


def get_question(session, base_url, course_id, question_id):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/questions/{question_id}"), timeout=5)


def create_quiz(session, base_url, course_id, title, description="", open_at="", due_at=""):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes"),
        json={
            "title": title,
            "description": description,
            "open_at": open_at,
            "due_at": due_at,
        },
        timeout=5,
    )


def add_quiz_question(session, base_url, course_id, quiz_id, question_id, points=1, position=1):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/questions"),
        json={"question_id": question_id, "points": points, "position": position},
        timeout=5,
    )


def start_attempt(session, base_url, course_id, quiz_id):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start"),
        timeout=5,
    )


def answer_question(session, base_url, course_id, quiz_id, attempt_id, question_id, answer_json):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers"),
        json={"question_id": question_id, "answer_json": answer_json},
        timeout=5,
    )


def submit_attempt(session, base_url, course_id, quiz_id, attempt_id):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit"),
        timeout=5,
    )


def upload_file(session, base_url, course_id, filename, content, content_type="application/octet-stream"):
    files = {"file": (filename, content, content_type)}
    return session.post(api_url(base_url, f"/api/courses/{course_id}/uploads"), files=files, timeout=5)


def list_uploads(session, base_url, course_id):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/uploads"), timeout=5)


def download_upload(session, base_url, course_id, upload_id):
    return session.get(api_url(base_url, f"/api/courses/{course_id}/uploads/{upload_id}/download"), timeout=5)


def create_submission(session, base_url, course_id, assignment_id, content_text, attachment_upload_id=None):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions"),
        json={"content_text": content_text, "attachment_upload_id": attachment_upload_id},
        timeout=5,
    )


def update_submission(session, base_url, course_id, assignment_id, submission_id, content_text, attachment_upload_id=None):
    return session.put(
        api_url(base_url, f"/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}"),
        json={"content_text": content_text, "attachment_upload_id": attachment_upload_id},
        timeout=5,
    )


def create_assignment(session, base_url, course_id, title, description="desc", due_at=""):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/assignments"),
        json={"title": title, "description": description, "due_at": due_at},
        timeout=5,
    )


def get_user_id(db_path, username):
    conn = sqlite3.connect(str(db_path))
    try:
        row = conn.execute("SELECT user_id FROM users WHERE username=?", (username,)).fetchone()
        return row[0] if row else None
    finally:
        conn.close()


def get_membership_id(db_path, course_id, user_id):
    conn = sqlite3.connect(str(db_path))
    try:
        row = conn.execute(
            "SELECT membership_id FROM memberships WHERE course_id=? AND user_id=?",
            (course_id, user_id),
        ).fetchone()
        return row[0] if row else None
    finally:
        conn.close()


def count_rows(db_path, sql, params=()):
    conn = sqlite3.connect(str(db_path))
    try:
        row = conn.execute(sql, params).fetchone()
        return row[0]
    finally:
        conn.close()


def get_row(db_path, sql, params=()):
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    try:
        return conn.execute(sql, params).fetchone()
    finally:
        conn.close()
