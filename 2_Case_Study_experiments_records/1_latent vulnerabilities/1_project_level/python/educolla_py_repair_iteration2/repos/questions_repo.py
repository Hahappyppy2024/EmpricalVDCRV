from util import now_iso, json_dumps

class QuestionsRepo:
    def __init__(self, db):
        self.db = db

    def create_question(self, course_id, created_by, qtype, prompt, options_json, answer_json):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO questions(course_id,created_by,qtype,prompt,options_json,answer_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)",
            (course_id, created_by, qtype, prompt, json_dumps(options_json), json_dumps(answer_json), ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def _student_question_fields(self):
        return "q.question_id, q.course_id, q.created_by, q.qtype, q.prompt, q.options_json, q.created_at, q.updated_at, u.username as creator_username"

    def _staff_question_fields(self):
        return "q.*, u.username as creator_username"

    def list_questions(self, course_id, include_answer=False):
        fields = self._staff_question_fields() if include_answer else self._student_question_fields()
        rows = self.db.execute(
            f"SELECT {fields} FROM questions q JOIN users u ON u.user_id=q.created_by WHERE q.course_id=? ORDER BY q.question_id DESC",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_question(self, course_id, question_id, include_answer=False):
        fields = self._staff_question_fields() if include_answer else self._student_question_fields()
        r = self.db.execute(
            f"SELECT {fields} FROM questions q JOIN users u ON u.user_id=q.created_by WHERE q.course_id=? AND q.question_id=?",
            (course_id, question_id),
        ).fetchone()
        return dict(r) if r else None

    def list_questions_for_staff(self, course_id):
        return self.list_questions(course_id, include_answer=True)

    def get_question_for_staff(self, course_id, question_id):
        return self.get_question(course_id, question_id, include_answer=True)

    def update_question(self, course_id, question_id, qtype, prompt, options_json, answer_json):
        self.db.execute(
            "UPDATE questions SET qtype=?, prompt=?, options_json=?, answer_json=?, updated_at=? WHERE course_id=? AND question_id=?",
            (qtype, prompt, json_dumps(options_json), json_dumps(answer_json), now_iso(), course_id, question_id),
        )
        self.db.commit()

    def delete_question(self, course_id, question_id):
        self.db.execute("DELETE FROM questions WHERE course_id=? AND question_id=?", (course_id, question_id))
        self.db.commit()