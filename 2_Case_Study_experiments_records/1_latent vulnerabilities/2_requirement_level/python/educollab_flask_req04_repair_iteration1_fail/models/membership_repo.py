from models.db import get_db


ALLOWED_ROLES = {'admin', 'teacher', 'student', 'assistant', 'senior-assistant'}


def add_membership(course_id, user_id, role_in_course):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO memberships (course_id, user_id, role_in_course) VALUES (?, ?, ?)',
        (course_id, user_id, role_in_course),
    )
    db.commit()
    return get_membership_by_id(cursor.lastrowid)


def get_membership_by_id(membership_id):
    db = get_db()
    return db.execute(
        'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at, '
        'u.username, u.display_name, c.title AS course_title '
        'FROM memberships m '
        'JOIN users u ON u.user_id = m.user_id '
        'JOIN courses c ON c.course_id = m.course_id '
        'WHERE m.membership_id = ?',
        (membership_id,),
    ).fetchone()


def get_membership_for_user(course_id, user_id):
    db = get_db()
    return db.execute(
        'SELECT membership_id, course_id, user_id, role_in_course, created_at '
        'FROM memberships WHERE course_id = ? AND user_id = ?',
        (course_id, user_id),
    ).fetchone()


def list_members(course_id):
    db = get_db()
    return db.execute(
        'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at, '
        'u.username, u.display_name '
        'FROM memberships m JOIN users u ON u.user_id = m.user_id '
        'WHERE m.course_id = ? ORDER BY m.membership_id ASC',
        (course_id,),
    ).fetchall()


def update_membership_role(membership_id, role_in_course):
    db = get_db()
    db.execute(
        'UPDATE memberships SET role_in_course = ? WHERE membership_id = ?',
        (role_in_course, membership_id),
    )
    db.commit()
    return get_membership_by_id(membership_id)


def remove_membership(membership_id):
    db = get_db()
    db.execute('DELETE FROM memberships WHERE membership_id = ?', (membership_id,))
    db.commit()


def list_memberships_for_user(user_id):
    db = get_db()
    return db.execute(
        'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at, '
        'c.title AS course_title '
        'FROM memberships m JOIN courses c ON c.course_id = m.course_id '
        'WHERE m.user_id = ? ORDER BY m.membership_id DESC',
        (user_id,),
    ).fetchall()
