import requests
from util_fl import (
    api_login, api_register, api_users, find_user_id_by_username,
    api_create_course, api_add_member, api_create_assignment,
    api_upload_file, api_create_submission, make_zip_bytes,
    fl_unzip_submission, fl_list_submission_files, rand
)


def test_FL_FR2_list_submission_files_functional():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    stu = requests.Session()
    uname = rand("stu")
    api_register(stu, uname, "pw12345", "Student")
    uid = find_user_id_by_username(api_users(admin), uname)
    assert uid is not None
    api_add_member(admin, course_id, uid, "student")

    assignment_id = api_create_assignment(admin, course_id, "A_" + rand("a"), "desc")

    api_login(stu, uname, "pw12345")
    zip_bytes = make_zip_bytes({"a.txt": b"a", "dir/b.txt": b"b"})
    upload_id = api_upload_file(stu, course_id, "sub.zip", zip_bytes, "application/zip")
    submission_id = api_create_submission(stu, course_id, assignment_id, "from student", attachment_upload_id=upload_id)

    fl_unzip_submission(admin, course_id, assignment_id, submission_id)
    data = fl_list_submission_files(admin, course_id, assignment_id, submission_id)
    assert "files" in data
    joined = "\n".join(data["files"])
    assert "a.txt" in joined and "b.txt" in joined
