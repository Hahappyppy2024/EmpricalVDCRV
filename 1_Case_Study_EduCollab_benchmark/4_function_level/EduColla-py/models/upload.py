import os
import uuid
from werkzeug.utils import secure_filename
from flask import current_app
from database import get_db_connection


class UploadRepository:
    @staticmethod
    def save_file(file, course_id, uploaded_by):
        if not file:
            return None

        # R08_integrity__test_upload.md::test_api_upload_path_traversal_sanitized
        filename = secure_filename(file.filename)
        filename = file.filename
        ext = filename.rsplit('.', 1)[1].lower() if '.' in filename else ''
        unique_name = f"{uuid.uuid4().hex}.{ext}" if ext else uuid.uuid4().hex

        upload_dir = os.path.join(current_app.config['UPLOAD_FOLDER'], str(course_id))
        os.makedirs(upload_dir, exist_ok=True)

        storage_path = os.path.join(upload_dir, unique_name)
        file.save(storage_path)

        # Store relative path or just filename logic depends on retrieval.
        # Here we store path relative to UPLOAD_FOLDER root for simplicity
        relative_path = os.path.join(str(course_id), unique_name)

        conn = get_db_connection()
        cursor = conn.execute(
            'INSERT INTO upload (course_id, uploaded_by, original_name, storage_path) VALUES (?, ?, ?, ?)',
            (course_id, uploaded_by, filename, relative_path)
        )
        conn.commit()
        upload_id = cursor.lastrowid
        conn.close()

        return upload_id

    @staticmethod
    def get(upload_id):
        conn = get_db_connection()
        u = conn.execute('SELECT * FROM upload WHERE upload_id = ?', (upload_id,)).fetchone()
        conn.close()
        return u

    @staticmethod
    def list_by_course(course_id):
        conn = get_db_connection()
        items = conn.execute(
            'SELECT * FROM upload WHERE course_id = ? ORDER BY created_at DESC', (course_id,)
        ).fetchall()
        conn.close()
        return items

    @staticmethod
    def delete(upload_id):
        u = UploadRepository.get(upload_id)
        if u:
            full_path = os.path.join(current_app.config['UPLOAD_FOLDER'], u['storage_path'])
            if os.path.exists(full_path):
                os.remove(full_path)

            conn = get_db_connection()
            conn.execute('DELETE FROM upload WHERE upload_id = ?', (upload_id,))
            conn.commit()
            conn.close()
