from util import now_iso

class PostsRepo:
    def __init__(self, db):
        self.db = db

    def create_post(self, course_id, author_id, title, body):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO posts(course_id,author_id,title,body,created_at,updated_at) VALUES (?,?,?,?,?,?)",
            (course_id, author_id, title, body, ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_posts(self, course_id):
        rows = self.db.execute(
            "SELECT p.*, u.username as author_username FROM posts p JOIN users u ON u.user_id=p.author_id WHERE p.course_id=? ORDER BY p.post_id DESC",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_post(self, course_id, post_id):
        r = self.db.execute(
            "SELECT p.*, u.username as author_username FROM posts p JOIN users u ON u.user_id=p.author_id WHERE p.course_id=? AND p.post_id=?",
            (course_id, post_id),
        ).fetchone()
        return dict(r) if r else None

    def update_post(self, course_id, post_id, title, body, author_id):
        self.db.execute(
            "UPDATE posts SET title=?, body=?, updated_at=? WHERE course_id=? AND post_id=? AND author_id=?",
            (title, body, now_iso(), course_id, post_id, author_id),
        )
        self.db.commit()

    def delete_post(self, course_id, post_id, author_id):
        self.db.execute(
            "DELETE FROM posts WHERE course_id=? AND post_id=? AND author_id=?",
            (course_id, post_id, author_id)
        )
        self.db.commit()

    def search_posts(self, course_id, keyword):
        like = f"%{keyword}%"
        rows = self.db.execute(
            "SELECT p.*, u.username as author_username FROM posts p JOIN users u ON u.user_id=p.author_id WHERE p.course_id=? AND (p.title LIKE ? OR p.body LIKE ?) ORDER BY p.post_id DESC",
            (course_id, like, like),
        ).fetchall()
        return [dict(r) for r in rows]