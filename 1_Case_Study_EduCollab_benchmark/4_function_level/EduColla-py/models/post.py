from database import get_db_connection


class PostRepository:
    @staticmethod
    def create(course_id, author_id, title, body):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO post (course_id, author_id, title, body) VALUES (?, ?, ?, ?)',
            (course_id, author_id, title, body)
        )
        conn.commit()
        post_id = cursor.lastrowid
        conn.close()
        return post_id

    @staticmethod
    def get(post_id):
        conn = get_db_connection()
        post = conn.execute('SELECT * FROM post WHERE post_id = ?', (post_id,)).fetchone()
        conn.close()
        return post

    @staticmethod
    def list_by_course(course_id):
        conn = get_db_connection()
        posts = conn.execute(
            'SELECT * FROM post WHERE course_id = ? ORDER BY created_at DESC', (course_id,)
        ).fetchall()
        conn.close()
        return posts

    @staticmethod
    def update(post_id, title, body):
        conn = get_db_connection()
        conn.execute(
            'UPDATE post SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE post_id = ?',
            (title, body, post_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(post_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM post WHERE post_id = ?', (post_id,))
        conn.commit()
        conn.close()


    def search(course_id: int, keyword: str):
        conn = get_db_connection()
        # VULNERABLE: string concatenation
        sql = f"""
            SELECT post_id, course_id, author_id, title, body, created_at, updated_at
            FROM post
            WHERE course_id = {course_id}
              AND (title LIKE '%{keyword}%' OR body LIKE '%{keyword}%')
            ORDER BY post_id DESC
        """
        rows = conn.execute(sql).fetchall()
        conn.close()
        return rows

class CommentRepository:
    @staticmethod
    def create(post_id, course_id, author_id, body):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO comment (post_id, course_id, author_id, body) VALUES (?, ?, ?, ?)',
            (post_id, course_id, author_id, body)
        )
        conn.commit()
        conn.close()
        return cursor.lastrowid

    @staticmethod
    def list_by_post(post_id):
        conn = get_db_connection()
        comments = conn.execute(
            'SELECT * FROM comment WHERE post_id = ? ORDER BY created_at ASC', (post_id,)
        ).fetchall()
        conn.close()
        return comments

    @staticmethod
    def get(comment_id):
        conn = get_db_connection()
        c = conn.execute('SELECT * FROM comment WHERE comment_id = ?', (comment_id,)).fetchone()
        conn.close()
        return c

    @staticmethod
    def update(comment_id, body):
        conn = get_db_connection()
        conn.execute(
            'UPDATE comment SET body = ?, updated_at = CURRENT_TIMESTAMP WHERE comment_id = ?',
            (body, comment_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(comment_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM comment WHERE comment_id = ?', (comment_id,))
        conn.commit()
        conn.close()
