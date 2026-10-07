from util import now_iso

class AssignmentsRepo:
    def __init__(self, db):
        self.db = db

    def create_assignment(self, course_id, created_by, title, description, due_at):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO assignments(course_id,created_by,title,description,due_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?)",
            (course_id, created_by, title, description, due_at, ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_assignments(self, course_id):
        rows = self.db.execute(
            "SELECT a.*, u.username as creator_username FROM assignments a JOIN users u ON u.user_id=a.created_by WHERE a.course_id=? ORDER BY a.assignment_id DESC",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_assignment(self, course_id, assignment_id):
        r = self.db.execute(
            "SELECT a.*, u.username as creator_username FROM assignments a JOIN users u ON u.user_id=a.created_by WHERE a.course_id=? AND a.assignment_id=?",
            (course_id, assignment_id),
        ).fetchone()
        return dict(r) if r else None

    def update_assignment(self, course_id, assignment_id, title, description, due_at):
        self.db.execute(
            "UPDATE assignments SET title=?, description=?, due_at=?, updated_at=? WHERE course_id=? AND assignment_id=?",
            (title, description, due_at, now_iso(), course_id, assignment_id),
        )
        self.db.commit()

    def delete_assignment(self, course_id, assignment_id):
        self.db.execute("DELETE FROM assignments WHERE course_id=? AND assignment_id=?", (course_id, assignment_id))
        self.db.commit()
