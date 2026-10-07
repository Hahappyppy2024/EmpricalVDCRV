from util import now_iso

class SubmissionsRepo:
    def __init__(self, db):
        self.db = db

    def create_submission(self, course_id, assignment_id, student_id, content_text, attachment_upload_id):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO submissions(assignment_id,course_id,student_id,content_text,attachment_upload_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)",
            (assignment_id, course_id, student_id, content_text, attachment_upload_id, ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def update_submission(self, course_id, assignment_id, submission_id, student_id, content_text, attachment_upload_id):
        self.db.execute(
            "UPDATE submissions SET content_text=?, attachment_upload_id=?, updated_at=? WHERE course_id=? AND assignment_id=? AND submission_id=? AND student_id=?",
            (content_text, attachment_upload_id, now_iso(), course_id, assignment_id, submission_id, student_id),
        )
        self.db.commit()

    def get_my_submission_for_assignment(self, course_id, assignment_id, student_id):
        r = self.db.execute(
            "SELECT * FROM submissions WHERE course_id=? AND assignment_id=? AND student_id=?",
            (course_id, assignment_id, student_id),
        ).fetchone()
        return dict(r) if r else None

    def list_my_submissions(self, student_id):
        rows = self.db.execute(
            "SELECT s.*, a.title as assignment_title, c.title as course_title FROM submissions s JOIN assignments a ON a.assignment_id=s.assignment_id JOIN courses c ON c.course_id=s.course_id WHERE s.student_id=? ORDER BY s.updated_at DESC",
            (student_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def list_for_assignment(self, course_id, assignment_id):
        rows = self.db.execute(
            "SELECT s.*, u.username as student_username FROM submissions s JOIN users u ON u.user_id=s.student_id WHERE s.course_id=? AND s.assignment_id=? ORDER BY s.updated_at DESC",
            (course_id, assignment_id),
        ).fetchall()
        return [dict(r) for r in rows]
