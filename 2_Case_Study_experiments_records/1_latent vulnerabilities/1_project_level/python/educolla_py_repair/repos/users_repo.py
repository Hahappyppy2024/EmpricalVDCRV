from typing import Optional
from werkzeug.security import generate_password_hash, check_password_hash
from util import now_iso

class UsersRepo:
    def __init__(self, db):
        self.db = db

    def create_user(self, username: str, password: str, display_name: str) -> int:
        cur = self.db.execute(
            "INSERT INTO users(username,password_hash,display_name,created_at) VALUES (?,?,?,?)",
            (username, generate_password_hash(password), display_name, now_iso()),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def ensure_admin(self, username="admin", password=None, display_name="Admin") -> int:
        row = self.db.execute("SELECT user_id FROM users WHERE username=?", (username,)).fetchone()
        if row:
            return int(row["user_id"])
        if not password:
            raise ValueError("admin bootstrap password required")
        return self.create_user(username, password, display_name)

    def get_by_username(self, username: str) -> Optional[dict]:
        r = self.db.execute(
            "SELECT user_id,username,display_name,created_at,password_hash FROM users WHERE username=?",
            (username,),
        ).fetchone()
        return dict(r) if r else None

    def get_by_id(self, user_id: int) -> Optional[dict]:
        r = self.db.execute(
            "SELECT user_id,username,display_name,created_at FROM users WHERE user_id=?",
            (user_id,),
        ).fetchone()
        return dict(r) if r else None

    def verify_login(self, username: str, password: str) -> Optional[dict]:
        u = self.get_by_username(username)
        if not u:
            return None
        if check_password_hash(u["password_hash"], password):
            return {"user_id": u["user_id"], "username": u["username"], "display_name": u["display_name"]}
        return None

    def list_users(self):
        rows = self.db.execute("SELECT user_id,username,display_name,created_at FROM users ORDER BY user_id").fetchall()
        return [dict(r) for r in rows]