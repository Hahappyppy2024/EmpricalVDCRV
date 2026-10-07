from database import get_db_connection

class CourseRepository:
    @staticmethod
    def create(title, description, created_by):
        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO course (title, description, created_by) VALUES (?, ?, ?)',
            (title, description, created_by)
        )
        conn.commit()
        course_id = cursor.lastrowid
        # Add creator as 'admin' member
        conn.execute(
            'INSERT INTO membership (course_id, user_id, role_in_course) VALUES (?, ?, ?)',
            (course_id, created_by, 'admin')
        )
        conn.commit()
        conn.close()
        return course_id

    @staticmethod
    def get(course_id):
        conn = get_db_connection()
        course = conn.execute('SELECT * FROM course WHERE course_id = ?', (course_id,)).fetchone()
        conn.close()
        return course

    @staticmethod
    def list_all():
        conn = get_db_connection()
        courses = conn.execute('SELECT * FROM course ORDER BY created_at DESC').fetchall()
        conn.close()
        return courses

    @staticmethod
    def update(course_id, title, description):
        conn = get_db_connection()
        conn.execute(
            'UPDATE course SET title = ?, description = ? WHERE course_id = ?',
            (title, description, course_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def delete(course_id):
        conn = get_db_connection()
        conn.execute('DELETE FROM course WHERE course_id = ?', (course_id,))
        conn.commit()
        conn.close()

    # Memberships
    @staticmethod
    def add_member(course_id, user_id, role):
        conn = get_db_connection()
        try:
            conn.execute(
                'INSERT INTO membership (course_id, user_id, role_in_course) VALUES (?, ?, ?)',
                (course_id, user_id, role)
            )
            conn.commit()
            return True
        except:
            return False
        finally:
            conn.close()

    @staticmethod
    def get_members(course_id):
        conn = get_db_connection()
        rows = conn.execute('''
            SELECT m.membership_id, m.role_in_course, u.user_id, u.username, u.display_name 
            FROM membership m 
            JOIN user u ON m.user_id = u.user_id 
            WHERE m.course_id = ?
        ''', (course_id,)).fetchall()
        conn.close()
        return rows

    @staticmethod
    def get_member_role(course_id, user_id):
        conn = get_db_connection()
        row = conn.execute(
            'SELECT role_in_course FROM membership WHERE course_id = ? AND user_id = ?',
            (course_id, user_id)
        ).fetchone()
        conn.close()
        return row['role_in_course'] if row else None

    @staticmethod
    def update_member_role(course_id, membership_id, new_role):
        conn = get_db_connection()
        conn.execute(
            'UPDATE membership SET role_in_course = ? WHERE membership_id = ? AND course_id = ?',
            (new_role, membership_id, course_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def remove_member(course_id, membership_id):
        conn = get_db_connection()
        conn.execute(
            'DELETE FROM membership WHERE membership_id = ? AND course_id = ?',
            (membership_id, course_id)
        )
        conn.commit()
        conn.close()

    @staticmethod
    def get_user_memberships(user_id):
        conn = get_db_connection()
        rows = conn.execute('''
            SELECT c.course_id, c.title, m.role_in_course 
            FROM membership m 
            JOIN course c ON m.course_id = c.course_id 
            WHERE m.user_id = ?
        ''', (user_id,)).fetchall()
        conn.close()
        return rows
