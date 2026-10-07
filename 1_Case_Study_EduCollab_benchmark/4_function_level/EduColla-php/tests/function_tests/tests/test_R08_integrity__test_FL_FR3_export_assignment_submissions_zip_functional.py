import requests, zipfile, io
from util_fl import (
    api_login, api_register, api_users, find_user_id_by_username,
    api_create_course, api_add_member, api_create_assignment,
    api_upload_file, api_create_submission, make_zip_bytes,
    fl_export_assignment_submissions_zip, fl_download_export_zip, rand
)

def test_FL_FR3_export_assignment_submissions_zip_functional():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    # student
    stu = requests.Session()
    uname = rand("stu")
    api_register(stu, uname, "pw12345", "Student")
    uid = find_user_id_by_username(api_users(admin), uname)
    assert uid is not None
    api_add_member(admin, course_id, uid, "student")

    assignment_id = api_create_assignment(admin, course_id, "A_" + rand("a"), "desc")

    # student submission with zip attachment
    api_login(stu, uname, "pw12345")
    zip_bytes = make_zip_bytes({"hello.txt": b"hello"})
    upload_id = api_upload_file(stu, course_id, "sub.zip", zip_bytes, "application/zip")
    api_create_submission(stu, course_id, assignment_id, "from student", attachment_upload_id=upload_id)

#     job_id = fl_export_assignment_submissions_zip(admin, course_id, assignment_id)
#     r = fl_download_export_zip(admin, course_id, assignment_id, job_id)
    job = fl_export_assignment_submissions_zip(admin, course_id, assignment_id)
    job_id = job["job_id"] if isinstance(job, dict) else job
    r = fl_download_export_zip(admin, course_id, assignment_id, job_id)
    assert "zip" in r.headers.get("Content-Type","") or r.headers.get("Content-Type","").startswith("application/zip")
    z = zipfile.ZipFile(io.BytesIO(r.content))
    assert len(z.namelist()) > 0
