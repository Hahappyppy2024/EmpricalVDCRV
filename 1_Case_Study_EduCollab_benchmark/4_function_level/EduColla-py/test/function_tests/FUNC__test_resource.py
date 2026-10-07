from conftest import add_membership
from _helpers import make_zip_bytes


def test_resource_upload_zip_list_and_download(app, client, admin_logged_in, course_with_admin):
    course_id = course_with_admin

    z = make_zip_bytes({"hello.txt": b"hi", "folder/a.txt": b"a"})
    rv = client.post(
        f"/courses/{course_id}/materials/upload-zip",
        data={"file": (z, "materials.zip")},
        content_type="multipart/form-data",
        follow_redirects=False,
    )
    assert rv.status_code == 201
    data = rv.get_json()
    assert "archive_id" in data
    archive_id = data["archive_id"]

    rv = client.get(f"/courses/{course_id}/materials/{archive_id}/files")
    assert rv.status_code == 200
    js = rv.get_json()
    names = {f["name"] for f in js["files"]}
    assert "hello.txt" in names

    rv = client.get(f"/courses/{course_id}/materials/{archive_id}/download.zip")
    assert rv.status_code == 200
    assert rv.mimetype == "application/zip"


def test_resource_export_members_csv(app, client, admin_logged_in, course_with_admin, student_user):
    course_id = course_with_admin
    add_membership(app, course_id, student_user, "student")

    rv = client.get(f"/courses/{course_id}/members/export.csv")
    assert rv.status_code == 200
    assert b"username" in rv.data
