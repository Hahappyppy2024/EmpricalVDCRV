from util import now_iso

class CoursesRepo:
    def __init__(self, db):
        self.db = db

    def create_course(self, title, description, created_by):
        cur = self.db.execute(
            "INSERT INTO courses(title,description,created_by,created_at) VALUES (?,?,?,?)",
            (title, description, created_by, now_iso()),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_courses(self):
        rows = self.db.execute(
            "SELECT c.*, u.username as creator_username FROM courses c JOIN users u ON u.user_id=c.created_by ORDER BY c.course_id DESC"
        ).fetchall()
        return [dict(r) for r in rows]

    def get_course(self, course_id):
        r = self.db.execute(
            "SELECT c.*, u.username as creator_username FROM courses c JOIN users u ON u.user_id=c.created_by WHERE c.course_id=?",
            (course_id,),
        ).fetchone()
        return dict(r) if r else None

    def update_course(self, course_id, title, description):
        self.db.execute("UPDATE courses SET title=?, description=? WHERE course_id=?", (title, description, course_id))
        self.db.commit()

    def delete_course(self, course_id):
        self.db.execute("DELETE FROM courses WHERE course_id=?", (course_id,))
        self.db.commit()
