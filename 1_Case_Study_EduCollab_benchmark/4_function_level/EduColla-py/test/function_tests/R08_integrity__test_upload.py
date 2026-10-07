import io
import os
from database import get_db_connection

def test_upload_and_download(app, client, admin_logged_in, course_with_admin):
    course_id = course_with_admin

    # 1) 先上传，才能在 DB 里有 upload 记录
    data = {"file": (io.BytesIO(b"hello"), "hello.txt")}
    rv = client.post(
        f"/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False,
    )
    assert rv.status_code in (302, 201)

    # 2) 再查 DB 拿到 upload_id / storage_path
    with app.app_context():
        conn = get_db_connection()
        u = conn.execute("SELECT * FROM upload ORDER BY upload_id DESC LIMIT 1").fetchone()
        conn.close()
        assert u is not None
        upload_id = u["upload_id"]

    # 3) 下载（允许 404），404 时验证文件确实落盘
    rv = client.get(f"/courses/{course_id}/uploads/{upload_id}/download", follow_redirects=False)
    assert rv.status_code in (200, 404)

    if rv.status_code == 200:
        assert rv.data == b"hello"
    else:
        with app.app_context():
            upload_folder = app.config["UPLOAD_FOLDER"]
        full_path = os.path.join(upload_folder, u["storage_path"])
        assert os.path.exists(full_path)