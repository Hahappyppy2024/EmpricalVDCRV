import json
import random
import string
import requests

def rand(prefix="u"):
    return prefix + "_" + "".join(random.choice(string.ascii_lowercase + string.digits) for _ in range(10))

def api_json(sess: requests.Session, method: str, url: str, **kwargs):
    headers = kwargs.pop("headers", {})
    headers.setdefault("Accept", "application/json")
    if "json" in kwargs:
        headers.setdefault("Content-Type", "application/json")
    return sess.request(method, url, headers=headers, **kwargs)

def assert_ok(r, expected=(200, 201, 204)):
    assert r.status_code in expected, f"HTTP {r.status_code}: {r.text}"

def login(sess, base_url, username, password):
    r = api_json(sess, "POST", f"{base_url}/api/auth/login", json={"username": username, "password": password})
    assert_ok(r, (200,))
    return r.json()

def register_student(sess, base_url, username, password, display_name):
    r = api_json(sess, "POST", f"{base_url}/api/auth/register",
                 json={"username": username, "password": password, "display_name": display_name})
    assert_ok(r, (200, 201))
    return r.json()

def me(sess, base_url):
    r = api_json(sess, "GET", f"{base_url}/api/auth/me")
    assert_ok(r, (200,))
    return r.json()

def create_course(sess, base_url, title, description):
    r = api_json(sess, "POST", f"{base_url}/api/courses", json={"title": title, "description": description})
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("course", data)

def add_member(sess, base_url, course_id, user_id, role):
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/members",
                 json={"user_id": user_id, "role_in_course": role})
    assert_ok(r, (200, 201))
    return r.json()

def create_post(sess, base_url, course_id, title, body):
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/posts", json={"title": title, "body": body})
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("post", data)

def list_posts(sess, base_url, course_id):
    r = api_json(sess, "GET", f"{base_url}/api/courses/{course_id}/posts")
    assert_ok(r, (200,))
    return r.json()

def create_comment(sess, base_url, course_id, post_id, body):
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/posts/{post_id}/comments", json={"body": body})
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("comment", data)

def search_posts(sess, base_url, course_id, keyword):
    r = api_json(sess, "GET", f"{base_url}/api/courses/{course_id}/search/posts", params={"keyword": keyword})
    assert_ok(r, (200,))
    return r.json()

def create_assignment(sess, base_url, course_id, title, description, due_at=None):
    payload = {"title": title, "description": description, "due_at": due_at}
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/assignments", json=payload)
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("assignment", data)

def create_submission(sess, base_url, course_id, assignment_id, content_text, attachment_upload_id=None):
    payload = {"content_text": content_text}
    if attachment_upload_id is not None:
        payload["attachment_upload_id"] = attachment_upload_id
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/assignments/{assignment_id}/submissions", json=payload)
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("submission", data)

def update_submission(sess, base_url, course_id, assignment_id, submission_id, content_text):
    r = api_json(sess, "PUT", f"{base_url}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}",
                 json={"content_text": content_text})
    assert_ok(r, (200,))
    data = r.json()
    return data.get("submission", data)

def list_submissions_for_assignment(sess, base_url, course_id, assignment_id):
    r = api_json(sess, "GET", f"{base_url}/api/courses/{course_id}/assignments/{assignment_id}/submissions")
    assert_ok(r, (200,))
    return r.json()

def upload_file(sess, base_url, course_id, filename="hello.txt", content=b"hello"):
    files = {"file": (filename, content, "application/octet-stream")}
    r = sess.post(f"{base_url}/api/courses/{course_id}/uploads", files=files, headers={"Accept":"application/json"})
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("upload", data)

def list_uploads(sess, base_url, course_id):
    r = api_json(sess, "GET", f"{base_url}/api/courses/{course_id}/uploads")
    assert_ok(r, (200,))
    return r.json()

def download_upload(sess, base_url, course_id, upload_id):
    r = sess.get(f"{base_url}/api/courses/{course_id}/uploads/{upload_id}/download")
    assert_ok(r, (200,))
    return r

def create_question(sess, base_url, course_id, qtype, prompt, options=None, answer=None):
    payload = {"qtype": qtype, "prompt": prompt}
    if options is not None:
        payload["options_json"] = json.dumps(options)
    if answer is not None:
        payload["answer_json"] = json.dumps(answer)
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/questions", json=payload)
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("question", data)

def create_quiz(sess, base_url, course_id, title, description="", open_at=None, due_at=None):
    payload = {"title": title, "description": description, "open_at": open_at, "due_at": due_at}
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/quizzes", json=payload)
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("quiz", data)

def add_quiz_question(sess, base_url, course_id, quiz_id, question_id, points=1, position=1):
    payload = {"question_id": question_id, "points": points, "position": position}
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/quizzes/{quiz_id}/questions", json=payload)
    assert_ok(r, (200, 201))
    return r.json()

def start_attempt(sess, base_url, course_id, quiz_id):
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start")
    assert_ok(r, (200, 201))
    data = r.json()
    return data.get("attempt", data)

def answer_question(sess, base_url, course_id, quiz_id, attempt_id, question_id, answer_obj):
    payload = {"question_id": question_id, "answer_json": json.dumps(answer_obj)}
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers", json=payload)
    assert_ok(r, (200, 201))
    return r.json()

def submit_attempt(sess, base_url, course_id, quiz_id, attempt_id):
    r = api_json(sess, "POST", f"{base_url}/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit")
    assert_ok(r, (200, 201))
    return r.json()
