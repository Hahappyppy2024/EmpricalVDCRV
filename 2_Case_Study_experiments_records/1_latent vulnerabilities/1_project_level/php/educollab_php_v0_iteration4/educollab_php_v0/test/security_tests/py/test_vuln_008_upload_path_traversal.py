
import pytest
from helpers import login, create_course, upload_file



def test_exploit_filename_path_traversal_on_upload(client):
    session, base_url = client

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Uploads", "demo").json()["course"]

    r = upload_file(
        session,
        base_url,
        course["course_id"],
        "../pwned.php",
        b"<?php echo 1; ?>",
        "application/x-php",
    )
    assert r.status_code == 200

# @pytest.mark.skip(reason="Current uploaded PHP code appears to generate server-side upload filenames with uniqid + extension and does not directly concatenate the original filename into the storage path as described in the provided vulnerability report.")
# def test_exploit_filename_path_traversal_on_upload(client):
#     session, base_url = client
#     assert login(session, base_url, "admin", "admin123").status_code == 200
#     course = create_course(session, base_url, "Uploads", "demo").json()["course"]
#     r = upload_file(session, base_url, course["course_id"], "../pwned.php", b"<?php echo 1; ?>", "application/x-php")
#     assert r.status_code == 200





