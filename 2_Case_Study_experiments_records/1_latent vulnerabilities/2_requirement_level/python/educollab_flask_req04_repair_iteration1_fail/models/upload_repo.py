from models.db import get_db



def create_upload(course_id, uploaded_by, original_name, storage_path):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO uploads (course_id, uploaded_by, original_name, storage_path) VALUES (?, ?, ?, ?)',
        (course_id, uploaded_by, original_name, storage_path),
    )
    db.commit()
    return get_upload_by_id(course_id, cursor.lastrowid)


def list_uploads(course_id):
    db = get_db()
    return db.execute(
        'SELECT up.upload_id, up.course_id, up.uploaded_by, up.original_name, up.storage_path, up.created_at, '
        'u.username, u.display_name '
        'FROM uploads up JOIN users u ON u.user_id = up.uploaded_by '
        'WHERE up.course_id = ? ORDER BY up.upload_id DESC',
        (course_id,),
    ).fetchall()


def get_upload_by_id(course_id, upload_id):
    db = get_db()
    return db.execute(
        'SELECT up.upload_id, up.course_id, up.uploaded_by, up.original_name, up.storage_path, up.created_at, '
        'u.username, u.display_name '
        'FROM uploads up JOIN users u ON u.user_id = up.uploaded_by '
        'WHERE up.course_id = ? AND up.upload_id = ?',
        (course_id, upload_id),
    ).fetchone()


def delete_upload(course_id, upload_id):
    db = get_db()
    db.execute('DELETE FROM uploads WHERE course_id = ? AND upload_id = ?', (course_id, upload_id))
    db.commit()
