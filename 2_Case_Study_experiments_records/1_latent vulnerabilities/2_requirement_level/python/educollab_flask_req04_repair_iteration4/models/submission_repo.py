from models.db import get_db



def create_submission(assignment_id, course_id, student_id, content_text, attachment_upload_id=None):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO submissions (assignment_id, course_id, student_id, content_text, attachment_upload_id) VALUES (?, ?, ?, ?, ?)',
        (assignment_id, course_id, student_id, content_text, attachment_upload_id),
    )
    db.commit()
    return get_submission_by_id(course_id, assignment_id, cursor.lastrowid)


def get_submission_by_id(course_id, assignment_id, submission_id):
    db = get_db()
    return db.execute(
        'SELECT s.submission_id, s.assignment_id, s.course_id, s.student_id, s.content_text, s.attachment_upload_id, s.created_at, s.updated_at, '
        'u.username, u.display_name, up.original_name AS attachment_name '
        'FROM submissions s '
        'JOIN users u ON u.user_id = s.student_id '
        'LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id '
        'WHERE s.course_id = ? AND s.assignment_id = ? AND s.submission_id = ?',
        (course_id, assignment_id, submission_id),
    ).fetchone()


def update_submission(course_id, assignment_id, submission_id, content_text, attachment_upload_id=None):
    db = get_db()
    db.execute(
        'UPDATE submissions SET content_text = ?, attachment_upload_id = ?, updated_at = CURRENT_TIMESTAMP WHERE course_id = ? AND assignment_id = ? AND submission_id = ?',
        (content_text, attachment_upload_id, course_id, assignment_id, submission_id),
    )
    db.commit()
    return get_submission_by_id(course_id, assignment_id, submission_id)


def list_submissions_for_assignment(course_id, assignment_id):
    db = get_db()
    return db.execute(
        'SELECT s.submission_id, s.assignment_id, s.course_id, s.student_id, s.content_text, s.attachment_upload_id, s.created_at, s.updated_at, '
        'u.username, u.display_name, up.original_name AS attachment_name '
        'FROM submissions s '
        'JOIN users u ON u.user_id = s.student_id '
        'LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id '
        'WHERE s.course_id = ? AND s.assignment_id = ? ORDER BY s.submission_id DESC',
        (course_id, assignment_id),
    ).fetchall()


def list_submissions_for_student(student_id):
    db = get_db()
    return db.execute(
        'SELECT s.submission_id, s.assignment_id, s.course_id, s.student_id, s.content_text, s.attachment_upload_id, s.created_at, s.updated_at, '
        'u.username, u.display_name, a.title AS assignment_title, c.title AS course_title, up.original_name AS attachment_name '
        'FROM submissions s '
        'JOIN users u ON u.user_id = s.student_id '
        'JOIN assignments a ON a.assignment_id = s.assignment_id '
        'JOIN courses c ON c.course_id = s.course_id '
        'LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id '
        'WHERE s.student_id = ? ORDER BY s.submission_id DESC',
        (student_id,),
    ).fetchall()