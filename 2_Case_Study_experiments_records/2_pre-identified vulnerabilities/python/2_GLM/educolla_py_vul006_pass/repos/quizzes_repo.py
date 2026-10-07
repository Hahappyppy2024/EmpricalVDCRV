from util import now_iso

class QuizzesRepo:
    def __init__(self, db):
        self.db = db

    def create_quiz(self, course_id, created_by, title, description, open_at, due_at):
        ts = now_iso()
        cur = self.db.execute(
            "INSERT INTO quizzes(course_id,created_by,title,description,open_at,due_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)",
            (course_id, created_by, title, description, open_at, due_at, ts, ts),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def list_quizzes(self, course_id):
        rows = self.db.execute(
            "SELECT qz.*, u.username as creator_username FROM quizzes qz JOIN users u ON u.user_id=qz.created_by WHERE qz.course_id=? ORDER BY qz.quiz_id DESC",
            (course_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def get_quiz(self, course_id, quiz_id):
        r = self.db.execute(
            "SELECT qz.*, u.username as creator_username FROM quizzes qz JOIN users u ON u.user_id=qz.created_by WHERE qz.course_id=? AND qz.quiz_id=?",
            (course_id, quiz_id),
        ).fetchone()
        return dict(r) if r else None

    def update_quiz(self, course_id, quiz_id, title, description, open_at, due_at):
        self.db.execute(
            "UPDATE quizzes SET title=?, description=?, open_at=?, due_at=?, updated_at=? WHERE course_id=? AND quiz_id=?",
            (title, description, open_at, due_at, now_iso(), course_id, quiz_id),
        )
        self.db.commit()

    def delete_quiz(self, course_id, quiz_id):
        self.db.execute("DELETE FROM quizzes WHERE course_id=? AND quiz_id=?", (course_id, quiz_id))
        self.db.commit()

    def add_quiz_question(self, quiz_id, question_id, points, position):
        self.db.execute(
            "INSERT OR REPLACE INTO quiz_questions(quiz_id, question_id, points, position) VALUES (?,?,?,?)",
            (quiz_id, question_id, points, position),
        )
        self.db.commit()

    def remove_quiz_question(self, quiz_id, question_id):
        self.db.execute("DELETE FROM quiz_questions WHERE quiz_id=? AND question_id=?", (quiz_id, question_id))
        self.db.commit()

    def list_quiz_questions(self, quiz_id):
        rows = self.db.execute(
            "SELECT qq.*, q.prompt, q.qtype FROM quiz_questions qq JOIN questions q ON q.question_id=qq.question_id WHERE qq.quiz_id=? ORDER BY qq.position ASC",
            (quiz_id,),
        ).fetchall()
        return [dict(r) for r in rows]

    def start_attempt(self, quiz_id, student_id):
        cur = self.db.execute(
            "INSERT INTO quiz_attempts(quiz_id,course_id,student_id,started_at,submitted_at) SELECT ?, course_id, ?, ?, NULL FROM quizzes WHERE quiz_id=?",
            (quiz_id, student_id, now_iso(), quiz_id),
        )
        self.db.commit()
        return int(cur.lastrowid)

    def get_attempt(self, attempt_id):
        r = self.db.execute("SELECT * FROM quiz_attempts WHERE attempt_id=?", (attempt_id,)).fetchone()
        return dict(r) if r else None

    def submit_attempt(self, attempt_id):
        self.db.execute("UPDATE quiz_attempts SET submitted_at=? WHERE attempt_id=?", (now_iso(), attempt_id))
        self.db.commit()

    def upsert_answer(self, attempt_id, question_id, answer_json_text):
        self.db.execute(
            "INSERT INTO quiz_answers(attempt_id,question_id,answer_json,created_at) VALUES (?,?,?,?) "
            "ON CONFLICT(attempt_id,question_id) DO UPDATE SET answer_json=excluded.answer_json, created_at=excluded.created_at",
            (attempt_id, question_id, answer_json_text, now_iso()),
        )
        self.db.commit()

    def list_my_attempts(self, student_id):
        rows = self.db.execute(
            "SELECT a.*, qz.title as quiz_title FROM quiz_attempts a JOIN quizzes qz ON qz.quiz_id=a.quiz_id WHERE a.student_id=? ORDER BY a.attempt_id DESC",
            (student_id,),
        ).fetchall()
        return [dict(r) for r in rows]