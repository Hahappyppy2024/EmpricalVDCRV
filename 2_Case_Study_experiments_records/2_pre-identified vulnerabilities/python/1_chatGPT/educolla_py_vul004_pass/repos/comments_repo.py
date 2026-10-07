from util import now_iso

class CommentsRepo:
    def __init__(self, db):
        self.db = db

    def create_comment(self, course_id, post_id, author_id, body):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO comments(post_id,course_id,author_id,body,created_at,updated_at) VALUES (?,?,?,?,?,?)",
            (post_id, course_id, author_id, body, ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_comments(self, course_id, post_id):
        rows = self.db.execute(
            "SELECT c.*, u.username as author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.post_id=? ORDER BY c.comment_id",
            (course_id, post_id),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_comment(self, course_id, post_id, comment_id):
        r = self.db.execute(
            "SELECT c.*, u.username as author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.post_id=? AND c.comment_id=?",
            (course_id, post_id, comment_id),
        ).fetchone()
        return dict(r) if r else None

    def update_comment_for_author(self, course_id, post_id, comment_id, author_id, body):
        self.db.execute(
            "UPDATE comments SET body=?, updated_at=? WHERE course_id=? AND post_id=? AND comment_id=? AND author_id=?",
            (body, now_iso(), course_id, post_id, comment_id, author_id),
        )
        self.db.commit()

    def update_comment_as_staff(self, course_id, post_id, comment_id, body):
        self.db.execute(
            "UPDATE comments SET body=?, updated_at=? WHERE course_id=? AND post_id=? AND comment_id=?",
            (body, now_iso(), course_id, post_id, comment_id),
        )
        self.db.commit()

    def update_comment(self, course_id, post_id, comment_id, body):
        self.db.execute(
            "UPDATE comments SET body=?, updated_at=? WHERE course_id=? AND post_id=? AND comment_id=?",
            (body, now_iso(), course_id, post_id, comment_id),
        )
        self.db.commit()

    def delete_comment_for_author(self, course_id, post_id, comment_id, author_id):
        self.db.execute("DELETE FROM comments WHERE course_id=? AND post_id=? AND comment_id=? AND author_id=?", (course_id, post_id, comment_id, author_id))
        self.db.commit()

    def delete_comment_as_staff(self, course_id, post_id, comment_id):
        self.db.execute("DELETE FROM comments WHERE course_id=? AND post_id=? AND comment_id=?", (course_id, post_id, comment_id))
        self.db.commit()

    def delete_comment(self, course_id, post_id, comment_id):
        self.db.execute("DELETE FROM comments WHERE course_id=? AND post_id=? AND comment_id=?", (course_id, post_id, comment_id))
        self.db.commit()

    def search_comments(self, course_id, keyword):
        like = f"%{keyword}%"
        rows = self.db.execute(
            "SELECT c.*, u.username as author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.body LIKE ? ORDER BY c.comment_id DESC",
            (course_id, like),
        ).fetchall()
        return [dict(r) for r in rows]