import json
from database import get_db_connection


class QuestionRepository:
    @staticmethod
    def create(course_id, created_by, qtype, prompt, options_json=None, answer_json=None):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO question (course_id, created_by, qtype, prompt, options_json, answer_json) VALUES (?, ?, ?, ?, ?, ?)',
            (course_id, created_by, qtype, prompt, options_json, answer_json)
        )
        conn.commit()
        conn.close()
        return cursor.lastrowid

    @staticmethod
    def get(question_id):
        conn = get_db_connection()
        q = conn.execute('SELECT * FROM question WHERE question_id = ?', (question_id,)).fetchone()
        conn.close()
        return q

    @staticmethod
    def list_by_course(course_id):
        conn = get_db_connection()
        items = conn.execute('SELECT * FROM question WHERE course_id = ? ORDER BY created_at DESC',
                             (course_id,)).fetchall()
        conn.close()
        return items

    @staticmethod
    def update(question_id, qtype, prompt, options_json, answer_json):
        conn = get_db_connection()
        conn.execute(
            'UPDATE question SET qtype=?, prompt=?, options_json=?, answer_json=?, updated_at=CURRENT_TIMESTAMP WHERE question_id=?',
            (qtype, prompt, options_json, answer_json, question_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(question_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM question WHERE question_id = ?', (question_id,))
        conn.commit()
        conn.close()


class QuizRepository:
    @staticmethod
    def create(course_id, created_by, title, description, open_at, due_at):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO quiz (course_id, created_by, title, description, open_at, due_at) VALUES (?, ?, ?, ?, ?, ?)',
            (course_id, created_by, title, description, open_at, due_at)
        )
        conn.commit()
        conn.close()
        return cursor.lastrowid

    @staticmethod
    def get(quiz_id):
        conn = get_db_connection()
        q = conn.execute('SELECT * FROM quiz WHERE quiz_id = ?', (quiz_id,)).fetchone()
        conn.close()
        return q

    @staticmethod
    def list_by_course(course_id):
        conn = get_db_connection()
        items = conn.execute('SELECT * FROM quiz WHERE course_id = ? ORDER BY due_at ASC', (course_id,)).fetchall()
        conn.close()
        return items

    @staticmethod
    def update(quiz_id, title, description, open_at, due_at):
        conn = get_db_connection()
        conn.execute(
            'UPDATE quiz SET title=?, description=?, open_at=?, due_at=?, updated_at=CURRENT_TIMESTAMP WHERE quiz_id=?',
            (title, description, open_at, due_at, quiz_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(quiz_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM quiz WHERE quiz_id = ?', (quiz_id,))
        conn.commit()
        conn.close()

    @staticmethod
    def add_question(quiz_id, question_id, points, position):
        conn = get_db_connection()
        conn.execute(
            'INSERT INTO quiz_question (quiz_id, question_id, points, position) VALUES (?, ?, ?, ?)',
            (quiz_id, question_id, points, position)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def remove_question(quiz_id, question_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM quiz_question WHERE quiz_id = ? AND question_id = ?', (quiz_id, question_id))
        conn.commit()
        conn.close()

    @staticmethod
    def get_questions(quiz_id):
        conn = get_db_connection()
        # Returns question details along with points/position in this quiz
        items = conn.execute('''
            SELECT q.*, qq.points, qq.position 
            FROM quiz_question qq 
            JOIN question q ON qq.question_id = q.question_id 
            WHERE qq.quiz_id = ? 
            ORDER BY qq.position ASC
        ''', (quiz_id,)).fetchall()
        conn.close()
        return items


class AttemptRepository:
    @staticmethod
    def start(quiz_id, course_id, student_id):
        conn = get_db_connection()
        # Check if there is an unsubmitted attempt
        existing = conn.execute(
            'SELECT * FROM quiz_attempt WHERE quiz_id = ? AND student_id = ? AND submitted_at IS NULL',
            (quiz_id, student_id)
        ).fetchone()
        if existing:
            conn.close()
            return existing['attempt_id']

        cursor = conn.execute(
            'INSERT INTO quiz_attempt (quiz_id, course_id, student_id) VALUES (?, ?, ?)',
            (quiz_id, course_id, student_id)
        )
        conn.commit()
        attempt_id = cursor.lastrowid
        conn.close()
        return attempt_id

    @staticmethod
    def get(attempt_id):
        conn = get_db_connection()
        a = conn.execute('SELECT * FROM quiz_attempt WHERE attempt_id = ?', (attempt_id,)).fetchone()
        conn.close()
        return a

    @staticmethod
    def get_active(quiz_id, student_id):
        conn = get_db_connection()
        a = conn.execute(
            'SELECT * FROM quiz_attempt WHERE quiz_id = ? AND student_id = ? AND submitted_at IS NULL',
            (quiz_id, student_id)
        ).fetchone()
        conn.close()
        return a

    @staticmethod
    def submit(attempt_id):
        conn = get_db_connection()
        conn.execute(
            'UPDATE quiz_attempt SET submitted_at = CURRENT_TIMESTAMP WHERE attempt_id = ?',
            (attempt_id,)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def save_answer(attempt_id, question_id, answer_json):
        conn = get_db_connection()
        # Upsert answer
        existing = conn.execute(
            'SELECT * FROM quiz_answer WHERE attempt_id = ? AND question_id = ?', (attempt_id, question_id)
        ).fetchone()
        if existing:
            conn.execute(
                'UPDATE quiz_answer SET answer_json = ? WHERE attempt_id = ? AND question_id = ?',
                (answer_json, attempt_id, question_id)
            )
        else:
            conn.execute(
                'INSERT INTO quiz_answer (attempt_id, question_id, answer_json) VALUES (?, ?, ?)',
                (attempt_id, question_id, answer_json)
            )
        conn.commit()
        conn.close()

    @staticmethod
    def get_my_attempts(student_id):
        conn = get_db_connection()
        items = conn.execute('''
            SELECT a.*, q.title as quiz_title, c.title as course_title 
            FROM quiz_attempt a 
            JOIN quiz q ON a.quiz_id = q.quiz_id 
            JOIN course c ON a.course_id = c.course_id
            WHERE a.student_id = ?
            ORDER BY a.started_at DESC
        ''', (student_id,)).fetchall()
        conn.close()
        return items
