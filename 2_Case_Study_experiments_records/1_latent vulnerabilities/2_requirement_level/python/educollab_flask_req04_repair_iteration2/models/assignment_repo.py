from models.db import get_db


def create_assignment(course_id, created_by, title, description, due_at):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO assignments (course_id, created_by, title, description, due_at) VALUES (?, ?, ?, ?, ?)',
        (course_id, created_by, title, description, due_at),
    )
    db.commit()
    return get_assignment_by_id(course_id, cursor.lastrowid)


def list_assignments(course_id):
    db = get_db()
    return db.execute(
        'SELECT a.assignment_id, a.course_id, a.created_by, a.title, a.description, a.due_at, a.created_at, a.updated_at, '
        'u.username, u.display_name '
        'FROM assignments a JOIN users u ON u.user_id = a.created_by '
        'WHERE a.course_id = ? ORDER BY a.assignment_id DESC',
        (course_id,),
    ).fetchall()


def get_assignment_by_id(course_id, assignment_id):
    db = get_db()
    return db.execute(
        'SELECT a.assignment_id, a.course_id, a.created_by, a.title, a.description, a.due_at, a.created_at, a.updated_at, '
        'u.username, u.display_name '
        'FROM assignments a JOIN users u ON u.user_id = a.created_by '
        'WHERE a.course_id = ? AND a.assignment_id = ?',
        (course_id, assignment_id),
    ).fetchone()


def update_assignment(course_id, assignment_id, title, description, due_at):
    db = get_db()
    db.execute(
        'UPDATE assignments SET title = ?, description = ?, due_at = ?, updated_at = CURRENT_TIMESTAMP WHERE course_id = ? AND assignment_id = ?',
        (title, description, due_at, course_id, assignment_id),
    )
    db.commit()
    return get_assignment_by_id(course_id, assignment_id)


def delete_assignment(course_id, assignment_id):
    db = get_db()
    db.execute('DELETE FROM assignments WHERE course_id = ? AND assignment_id = ?', (course_id, assignment_id))
    db.commit()
