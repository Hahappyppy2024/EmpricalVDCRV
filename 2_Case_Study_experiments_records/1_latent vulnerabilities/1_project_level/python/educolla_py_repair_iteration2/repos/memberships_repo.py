from util import now_iso

ROLE_SET = {"admin","teacher","student","assistant","senior-assistant"}
STAFF_ROLES = {"teacher","assistant","senior-assistant","admin"}

class MembershipsRepo:
    def __init__(self, db):
        self.db = db

    def add_member(self, course_id, user_id, role_in_course):
        cur = self.db.execute(
            "INSERT OR REPLACE INTO memberships(course_id,user_id,role_in_course,created_at) VALUES (?,?,?,?)",
            (course_id, user_id, role_in_course, now_iso()),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_members(self, course_id):
        rows = self.db.execute(
            "SELECT m.*, u.username, u.display_name FROM memberships m JOIN users u ON u.user_id=m.user_id WHERE m.course_id=? ORDER BY m.membership_id",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_user_role(self, course_id, user_id):
        r = self.db.execute("SELECT role_in_course FROM memberships WHERE course_id=? AND user_id=?", (course_id, user_id)).fetchone()
        return r["role_in_course"] if r else None

    def get_membership(self, membership_id):
        r = self.db.execute(
            "SELECT m.*, u.username, u.display_name FROM memberships m JOIN users u ON u.user_id=m.user_id WHERE m.membership_id=?",
            (membership_id,),
        ).fetchone()
        return dict(r) if r else None

    def get_course_membership(self, course_id, membership_id):
        r = self.db.execute(
            "SELECT m.*, u.username, u.display_name FROM memberships m JOIN users u ON u.user_id=m.user_id WHERE m.course_id=? AND m.membership_id=?",
            (course_id, membership_id),
        ).fetchone()
        return dict(r) if r else None

    def update_role(self, course_id, membership_id, role_in_course):
        self.db.execute(
            "UPDATE memberships SET role_in_course=? WHERE membership_id=? AND course_id=?",
            (role_in_course, membership_id, course_id),
        )
        self.db.commit()

    def remove_member(self, course_id, membership_id):
        self.db.execute("DELETE FROM memberships WHERE membership_id=? AND course_id=?", (membership_id, course_id))
        self.db.commit()

    def my_memberships(self, user_id):
        rows = self.db.execute(
            "SELECT m.*, c.title as course_title FROM memberships m JOIN courses c ON c.course_id=m.course_id WHERE m.user_id=? ORDER BY m.membership_id DESC",
            (user_id,),
        ).fetchall()
        return [dict(r) for r in rows]