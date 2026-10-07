from database import get_db_connection

class AssignmentRepository:
    @staticmethod
    def create(course_id, created_by, title, description, due_at):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO assignment (course_id, created_by, title, description, due_at) VALUES (?, ?, ?, ?, ?)',
            (course_id, created_by, title, description, due_at)
        )
        conn.commit()
        conn.close()
        return cursor.lastrowid

    @staticmethod
    def get(assignment_id):
        conn = get_db_connection()
        a = conn.execute('SELECT * FROM assignment WHERE assignment_id = ?', (assignment_id,)).fetchone()
        conn.close()
        return a

    @staticmethod
    def list_by_course(course_id):
        conn = get_db_connection()
        items = conn.execute('SELECT * FROM assignment WHERE course_id = ? ORDER BY due_at ASC', (course_id,)).fetchall()
        conn.close()
        return items

    @staticmethod
    def update(assignment_id, title, description, due_at):
        conn = get_db_connection()
        conn.execute(
            'UPDATE assignment SET title = ?, description = ?, due_at = ?, updated_at = CURRENT_TIMESTAMP WHERE assignment_id = ?',
            (title, description, due_at, assignment_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(assignment_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM assignment WHERE assignment_id = ?', (assignment_id,))
        conn.commit()
        conn.close()

class SubmissionRepository:
    @staticmethod
    def create(assignment_id, course_id, student_id, content_text, upload_id=None):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO submission (assignment_id, course_id, student_id, content_text, attachment_upload_id) VALUES (?, ?, ?, ?, ?)',
            (assignment_id, course_id, student_id, content_text, upload_id)
        )
        conn.commit()
        conn.close()
        return cursor.lastrowid

    @staticmethod
    def get(submission_id):
        conn = get_db_connection()
        s = conn.execute('SELECT * FROM submission WHERE submission_id = ?', (submission_id,)).fetchone()
        conn.close()
        return s

    @staticmethod
    def get_by_student(assignment_id, student_id):
        conn = get_db_connection()
        s = conn.execute(
            'SELECT * FROM submission WHERE assignment_id = ? AND student_id = ?',
            (assignment_id, student_id)
        ).fetchone()
        conn.close()
        return s

    @staticmethod
    def list_for_assignment(assignment_id):
        conn = get_db_connection()
        items = conn.execute('''
            SELECT s.*, u.display_name, u.username 
            FROM submission s 
            JOIN user u ON s.student_id = u.user_id 
            WHERE s.assignment_id = ?
        ''', (assignment_id,)).fetchall()
        conn.close()
        return items

    @staticmethod
    def update(submission_id, content_text, upload_id=None):
        conn = get_db_connection()
        conn.execute(
            'UPDATE submission SET content_text = ?, attachment_upload_id = ?, updated_at = CURRENT_TIMESTAMP WHERE submission_id = ?',
            (content_text, upload_id, submission_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def list_my_submissions(student_id):
        conn = get_db_connection()
        items = conn.execute('''
            SELECT s.*, a.title as assignment_title, c.title as course_title 
            FROM submission s
            JOIN assignment a ON s.assignment_id = a.assignment_id
            JOIN course c ON s.course_id = c.course_id
            WHERE s.student_id = ?
        ''', (student_id,)).fetchall()
        conn.close()
        return items
