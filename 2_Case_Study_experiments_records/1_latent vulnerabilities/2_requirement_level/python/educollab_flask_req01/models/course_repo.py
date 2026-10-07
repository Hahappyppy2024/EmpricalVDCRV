from models.db import get_db


def create_course(title, description, created_by):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO courses (title, description, created_by) VALUES (?, ?, ?)',
        (title, description, created_by),
    )
    db.commit()
    return get_course_by_id(cursor.lastrowid)


def list_courses():
    db = get_db()
    return db.execute(
        'SELECT c.course_id, c.title, c.description, c.created_by, c.created_at, u.display_name AS creator_name '
        'FROM courses c JOIN users u ON u.user_id = c.created_by ORDER BY c.course_id DESC'
    ).fetchall()


def get_course_by_id(course_id):
    db = get_db()
    return db.execute(
        'SELECT c.course_id, c.title, c.description, c.created_by, c.created_at, u.display_name AS creator_name '
        'FROM courses c JOIN users u ON u.user_id = c.created_by WHERE c.course_id = ?',
        (course_id,),
    ).fetchone()


def update_course(course_id, title, description):
    db = get_db()
    db.execute(
        'UPDATE courses SET title = ?, description = ? WHERE course_id = ?',
        (title, description, course_id),
    )
    db.commit()
    return get_course_by_id(course_id)


def delete_course(course_id):
    db = get_db()
    db.execute('DELETE FROM courses WHERE course_id = ?', (course_id,))
    db.commit()
