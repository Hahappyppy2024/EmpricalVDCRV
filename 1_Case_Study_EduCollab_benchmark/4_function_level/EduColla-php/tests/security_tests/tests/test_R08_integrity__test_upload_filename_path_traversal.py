"""Upload/path check: uploaded filename must not allow path traversal; download header must use basename only."""

import io
from common import api_login_admin, api_create_course, BASE_URL


def test_upload_filename_path_traversal_is_neutered_in_download_header():
    admin = api_login_admin()
    course = api_create_course(admin, title='upload-course')

    files = {
        'file': ('../evil.txt', io.BytesIO(b'hello'), 'text/plain')
    }
    r = admin.post(f"{BASE_URL}/api/courses/{course['course_id']}/uploads", files=files, timeout=10)
    assert r.status_code == 200, r.text
    up = r.json()['upload']

    # storage_path must stay inside storage/uploads/<course_id>/...
    assert 'storage/uploads/' in up['storage_path']
    assert '..' not in up['storage_path']

    # download should return attachment filename=evil.txt (basename)
    r = admin.get(f"{BASE_URL}/api/courses/{course['course_id']}/uploads/{up['upload_id']}/download", timeout=10)
    assert r.status_code == 200
    cd = r.headers.get('Content-Disposition', '')
    assert 'attachment' in cd.lower()
    assert 'evil.txt' in cd
    assert '..' not in cd
    assert '/' not in cd and '\\' not in cd
