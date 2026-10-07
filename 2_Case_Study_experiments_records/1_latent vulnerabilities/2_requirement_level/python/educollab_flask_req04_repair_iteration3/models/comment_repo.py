from models.db import get_db


def create_comment(post_id, course_id, author_id, body):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO comments (post_id, course_id, author_id, body) VALUES (?, ?, ?, ?)',
        (post_id, course_id, author_id, body),
    )
    db.commit()
    return get_comment_by_id(course_id, post_id, cursor.lastrowid)



def list_comments(course_id, post_id):
    db = get_db()
    return db.execute(
        'SELECT c.comment_id, c.post_id, c.course_id, c.author_id, c.body, c.created_at, c.updated_at, '
        'u.username, u.display_name '
        'FROM comments c JOIN users u ON u.user_id = c.author_id '
        'WHERE c.course_id = ? AND c.post_id = ? ORDER BY c.comment_id ASC',
        (course_id, post_id),
    ).fetchall()



def get_comment_by_id(course_id, post_id, comment_id):
    db = get_db()
    return db.execute(
        'SELECT c.comment_id, c.post_id, c.course_id, c.author_id, c.body, c.created_at, c.updated_at, '
        'u.username, u.display_name '
        'FROM comments c JOIN users u ON u.user_id = c.author_id '
        'WHERE c.course_id = ? AND c.post_id = ? AND c.comment_id = ?',
        (course_id, post_id, comment_id),
    ).fetchone()



def update_comment(course_id, post_id, comment_id, body):
    db = get_db()
    db.execute(
        'UPDATE comments SET body = ?, updated_at = CURRENT_TIMESTAMP '
        'WHERE course_id = ? AND post_id = ? AND comment_id = ?',
        (body, course_id, post_id, comment_id),
    )
    db.commit()
    return get_comment_by_id(course_id, post_id, comment_id)



def delete_comment(course_id, post_id, comment_id):
    db = get_db()
    db.execute(
        'DELETE FROM comments WHERE course_id = ? AND post_id = ? AND comment_id = ?',
        (course_id, post_id, comment_id),
    )
    db.commit()



def search_comments(course_id, keyword):
    db = get_db()
    term = f'%{keyword}%'
    return db.execute(
        'SELECT c.comment_id, c.post_id, c.course_id, c.author_id, c.body, c.created_at, c.updated_at, '
        'u.username, u.display_name '
        'FROM comments c JOIN users u ON u.user_id = c.author_id '
        'WHERE c.course_id = ? AND c.body LIKE ? '
        'ORDER BY c.comment_id DESC',
        (course_id, term),
    ).fetchall()
