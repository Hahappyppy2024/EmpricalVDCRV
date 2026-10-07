from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import requests


@dataclass
class ApiClient:
    base_url: str
    session: requests.Session

    @classmethod
    def new(cls, base_url: str) -> "ApiClient":
        return cls(base_url=base_url, session=requests.Session())

    def _url(self, path: str) -> str:
        if path.startswith("http://") or path.startswith("https://"):
            return path
        return f"{self.base_url}{path}"

    def get(self, path: str, **kwargs) -> requests.Response:
        return self.session.get(self._url(path), timeout=5, **kwargs)

    def post(self, path: str, **kwargs) -> requests.Response:
        return self.session.post(self._url(path), timeout=5, **kwargs)

    def put(self, path: str, **kwargs) -> requests.Response:
        return self.session.put(self._url(path), timeout=5, **kwargs)

    def delete(self, path: str, **kwargs) -> requests.Response:
        return self.session.delete(self._url(path), timeout=5, **kwargs)

    def register(self, username: str, password: str, display_name: str | None = None) -> dict[str, Any]:
        r = self.post(
            "/api/auth/register",
            json={
                "username": username,
                "password": password,
                "display_name": display_name or username,
            },
        )
        assert r.status_code == 200, r.text
        return r.json()["user"]

    def login(self, username: str, password: str) -> dict[str, Any]:
        r = self.post("/api/auth/login", json={"username": username, "password": password})
        assert r.status_code == 200, r.text
        return r.json()["user"]

    def me(self) -> dict[str, Any]:
        r = self.get("/api/auth/me")
        assert r.status_code == 200, r.text
        return r.json()["user"]

    def logout(self) -> None:
        r = self.post("/api/auth/logout")
        assert r.status_code == 200, r.text

    def list_users(self) -> list[dict[str, Any]]:
        r = self.get("/api/users")
        assert r.status_code == 200, r.text
        return r.json()["users"]

    def create_course(self, title: str, description: str = "demo") -> dict[str, Any]:
        r = self.post("/api/courses", json={"title": title, "description": description})
        assert r.status_code == 200, r.text
        return r.json()["course"]

    def add_member(self, course_id: int, user_id: int, role: str) -> dict[str, Any]:
        r = self.post(
            f"/api/courses/{course_id}/members",
            json={"user_id": user_id, "role_in_course": role},
        )
        assert r.status_code == 200, r.text
        return r.json()["membership"]

    def list_members(self, course_id: int) -> list[dict[str, Any]]:
        r = self.get(f"/api/courses/{course_id}/members")
        assert r.status_code == 200, r.text
        return r.json()["members"]

    def update_member_role(self, course_id: int, membership_id: int, role: str) -> requests.Response:
        return self.put(
            f"/api/courses/{course_id}/members/{membership_id}",
            json={"role_in_course": role},
        )

    def create_post(self, course_id: int, title: str, body: str) -> dict[str, Any]:
        r = self.post(f"/api/courses/{course_id}/posts", json={"title": title, "body": body})
        assert r.status_code == 200, r.text
        return r.json()["post"]

    def get_post(self, course_id: int, post_id: int) -> dict[str, Any]:
        r = self.get(f"/api/courses/{course_id}/posts/{post_id}")
        assert r.status_code == 200, r.text
        return r.json()["post"]

    def update_post(self, course_id: int, post_id: int, title: str, body: str) -> requests.Response:
        return self.put(f"/api/courses/{course_id}/posts/{post_id}", json={"title": title, "body": body})

    def create_comment(self, course_id: int, post_id: int, body: str) -> dict[str, Any]:
        r = self.post(
            f"/api/courses/{course_id}/posts/{post_id}/comments",
            json={"body": body},
        )
        assert r.status_code == 200, r.text
        return r.json()["comment"]

    def list_comments(self, course_id: int, post_id: int) -> list[dict[str, Any]]:
        r = self.get(f"/api/courses/{course_id}/posts/{post_id}/comments")
        assert r.status_code == 200, r.text
        return r.json()["comments"]

    def update_comment(self, course_id: int, post_id: int, comment_id: int, body: str) -> requests.Response:
        return self.put(
            f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}",
            json={"body": body},
        )

    def create_question(
        self,
        course_id: int,
        prompt: str,
        answer_json: str,
        qtype: str = "text",
        options_json: str | None = None,
    ) -> dict[str, Any]:
        r = self.post(
            f"/api/courses/{course_id}/questions",
            json={
                "qtype": qtype,
                "prompt": prompt,
                "answer_json": answer_json,
                "options_json": options_json,
            },
        )
        assert r.status_code == 200, r.text
        return r.json()["question"]

    def get_question(self, course_id: int, question_id: int) -> dict[str, Any]:
        r = self.get(f"/api/courses/{course_id}/questions/{question_id}")
        assert r.status_code == 200, r.text
        return r.json()["question"]

    def create_quiz(self, course_id: int, title: str, description: str = "demo") -> dict[str, Any]:
        r = self.post(
            f"/api/courses/{course_id}/quizzes",
            json={"title": title, "description": description, "open_at": "", "due_at": ""},
        )
        assert r.status_code == 200, r.text
        return r.json()["quiz"]

    def add_quiz_question(self, course_id: int, quiz_id: int, question_id: int, points: int = 1, position: int = 1) -> requests.Response:
        return self.post(
            f"/api/courses/{course_id}/quizzes/{quiz_id}/questions",
            json={"question_id": question_id, "points": points, "position": position},
        )

    def get_quiz(self, course_id: int, quiz_id: int) -> dict[str, Any]:
        r = self.get(f"/api/courses/{course_id}/quizzes/{quiz_id}")
        assert r.status_code == 200, r.text
        return r.json()

    def start_attempt(self, course_id: int, quiz_id: int) -> requests.Response:
        return self.post(f"/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start")


def user_id_by_username(users: list[dict[str, Any]], username: str) -> int:
    for user in users:
        if user["username"] == username:
            return int(user["user_id"])
    raise KeyError(f"username not found: {username}")


def membership_id_by_user(members: list[dict[str, Any]], user_id: int) -> int:
    for member in members:
        if int(member["user_id"]) == int(user_id):
            return int(member["membership_id"])
    raise KeyError(f"membership not found for user_id={user_id}")
