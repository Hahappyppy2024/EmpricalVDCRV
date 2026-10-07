import requests


class ApiClient:
    def __init__(self, base_url, session=None):
        self.base_url = base_url.rstrip("/")
        self.session = session or requests.Session()

    @classmethod
    def new(cls, base_url):
        return cls(base_url)

    def _url(self, path):
        return f"{self.base_url}{path}"

    def _json(self, resp):
        assert resp.status_code in (200, 201), resp.text
        data = resp.json()
        if isinstance(data, dict) and "data" in data and data["data"] is not None:
            return data["data"]
        return data

    def register(self, username, password="pass123", display_name=None):
        resp = self.session.post(
            self._url("/api/auth/register"),
            json={
                "username": username,
                "password": password,
                "display_name": display_name or username,
            },
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "user" in data:
            return data["user"]
        return data

    def login(self, username, password="pass123"):
        resp = self.session.post(
            self._url("/api/auth/login"),
            json={"username": username, "password": password},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "user" in data:
            return data["user"]
        return data

    def create_course(self, title, description="demo"):
        resp = self.session.post(
            self._url("/api/courses"),
            json={"title": title, "description": description},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "course" in data:
            return data["course"]
        return data

    def add_member(self, course_id, user_id, role):
        resp = self.session.post(
            self._url(f"/api/courses/{course_id}/members"),
            json={"user_id": user_id, "role_in_course": role},
            timeout=5,
        )
        return self._json(resp)

    def list_members(self, course_id):
        resp = self.session.get(
            self._url(f"/api/courses/{course_id}/members"),
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "members" in data:
            return data["members"]
        return data

    def update_member_role(self, course_id, membership_id, role):
        return self.session.put(
            self._url(f"/api/courses/{course_id}/members/{membership_id}"),
            json={"role_in_course": role},
            timeout=5,
        )

    def create_post(self, course_id, title, body):
        resp = self.session.post(
            self._url(f"/api/courses/{course_id}/posts"),
            json={"title": title, "body": body},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "post" in data:
            return data["post"]
        return data

    def update_post(self, course_id, post_id, title, body):
        return self.session.put(
            self._url(f"/api/courses/{course_id}/posts/{post_id}"),
            json={"title": title, "body": body},
            timeout=5,
        )

    def get_post(self, course_id, post_id):
        resp = self.session.get(
            self._url(f"/api/courses/{course_id}/posts/{post_id}"),
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "post" in data:
            return data["post"]
        return data

    def create_comment(self, course_id, post_id, body):
        resp = self.session.post(
            self._url(f"/api/courses/{course_id}/posts/{post_id}/comments"),
            json={"body": body},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "comment" in data:
            return data["comment"]
        return data

    def update_comment(self, course_id, post_id, comment_id, body):
        return self.session.put(
            self._url(f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
            json={"body": body},
            timeout=5,
        )

    def list_comments(self, course_id, post_id):
        resp = self.session.get(
            self._url(f"/api/courses/{course_id}/posts/{post_id}/comments"),
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "comments" in data:
            return data["comments"]
        return data

    def create_question(self, course_id, prompt, answer_json):
        resp = self.session.post(
            self._url(f"/api/courses/{course_id}/questions"),
            json={"prompt": prompt, "answer_json": answer_json},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "question" in data:
            return data["question"]
        return data

    def get_question(self, course_id, question_id):
        resp = self.session.get(
            self._url(f"/api/courses/{course_id}/questions/{question_id}"),
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "question" in data:
            return data["question"]
        return data

    def create_quiz(self, course_id, title):
        resp = self.session.post(
            self._url(f"/api/courses/{course_id}/quizzes"),
            json={"title": title},
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "quiz" in data:
            return data["quiz"]
        return data

    def add_quiz_question(self, course_id, quiz_id, question_id, points=1, position=1):
        return self.session.post(
            self._url(f"/api/courses/{course_id}/quizzes/{quiz_id}/questions"),
            json={
                "question_id": question_id,
                "points": points,
                "position": position,
            },
            timeout=5,
        )

    def get_quiz(self, course_id, quiz_id):
        resp = self.session.get(
            self._url(f"/api/courses/{course_id}/quizzes/{quiz_id}"),
            timeout=5,
        )
        data = self._json(resp)
        if isinstance(data, dict) and "quiz" in data:
            return data["quiz"]
        return data

    def start_attempt(self, course_id, quiz_id):
        return self.session.post(
            self._url(f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start"),
            json={},
            timeout=5,
        )


def membership_id_by_user(members, user_id):
    for m in members:
        if int(m["user_id"]) == int(user_id):
            return m["membership_id"]
    raise AssertionError(f"membership not found for user_id={user_id}")