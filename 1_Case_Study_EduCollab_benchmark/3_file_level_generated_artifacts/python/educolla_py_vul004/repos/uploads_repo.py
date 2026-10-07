from util import now_iso

class UploadsRepo:
    def __init__(self, db):
        self.db = db

    def create_upload(self, course_id, uploaded_by, original_name, storage_path):
        cur = self.db.execute(
            "INSERT INTO uploads(course_id,uploaded_by,original_name,storage_path,created_at) VALUES (?,?,?,?,?)",
            (course_id, uploaded_by, original_name, storage_path, now_iso()),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_uploads(self, course_id):
        rows = self.db.execute(
            "SELECT up.*, u.username as uploaded_by_username FROM uploads up JOIN users u ON u.user_id=up.uploaded_by WHERE up.course_id=? ORDER BY up.upload_id DESC",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_upload(self, course_id, upload_id):
        r = self.db.execute(
            "SELECT up.*, u.username as uploaded_by_username FROM uploads up JOIN users u ON u.user_id=up.uploaded_by WHERE up.course_id=? AND up.upload_id=?",
            (course_id, upload_id),
        ).fetchone()
        return dict(r) if r else None

    def delete_upload(self, course_id, upload_id):
        self.db.execute("DELETE FROM uploads WHERE course_id=? AND upload_id=?", (course_id, upload_id))
        self.db.commit()
