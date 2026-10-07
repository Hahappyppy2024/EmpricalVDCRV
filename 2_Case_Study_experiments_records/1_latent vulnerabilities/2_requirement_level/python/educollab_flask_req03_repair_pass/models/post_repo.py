from models.db import get_db


def create_post(course_id, author_id, title, body):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO posts (course_id, author_id, title, body) VALUES (?, ?, ?, ?)',
        (course_id, author_id, title, body),
    )
    db.commit()
    return get_post_by_id(course_id, cursor.lastrowid)


def list_posts(course_id):
    db = get_db()
    return db.execute(
        'SELECT p.post_id, p.course_id, p.author_id, p.title, p.body, p.created_at, p.updated_at, '
        'u.username, u.display_name '
        'FROM posts p JOIN users u ON u.user_id = p.author_id '
        'WHERE p.course_id = ? ORDER BY p.post_id DESC',
        (course_id,),
    ).fetchall()


def get_post_by_id(course_id, post_id):
    db = get_db()
    return db.execute(
        'SELECT p.post_id, p.course_id, p.author_id, p.title, p.body, p.created_at, p.updated_at, '
        'u.username, u.display_name '
        'FROM posts p JOIN users u ON u.user_id = p.author_id '
        'WHERE p.course_id = ? AND p.post_id = ?',
        (course_id, post_id),
    ).fetchone()


def update_post(course_id, post_id, title, body, author_id=None):
    db = get_db()
    query = 'UPDATE posts SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE course_id = ? AND post_id = ?'
    params = [title, body, course_id, post_id]
    if author_id is not None:
        query += ' AND author_id = ?'
        params.append(author_id)
    db.execute(query, tuple(params))
    db.commit()
    return get_post_by_id(course_id, post_id)


def delete_post(course_id, post_id, author_id=None):
    db = get_db()
    post_query = 'DELETE FROM posts WHERE course_id = ? AND post_id = ?'
    post_params = [course_id, post_id]
    if author_id is not None:
        post_query += ' AND author_id = ?'
        post_params.append(author_id)
    db.execute(post_query, tuple(post_params))
    if author_id is None or db.total_changes:
        db.execute('DELETE FROM comments WHERE course_id = ? AND post_id = ?', (course_id, post_id))
    db.commit()


def search_posts(course_id, keyword):
    db = get_db()
    term = f'%{keyword}%'
    return db.execute(
        'SELECT p.post_id, p.course_id, p.author_id, p.title, p.body, p.created_at, p.updated_at, '
        'u.username, u.display_name '
        'FROM posts p JOIN users u ON u.user_id = p.author_id '
        'WHERE p.course_id = ? AND (p.title LIKE ? OR p.body LIKE ?) '
        'ORDER BY p.post_id DESC',
        (course_id, term, term),
    ).fetchall()