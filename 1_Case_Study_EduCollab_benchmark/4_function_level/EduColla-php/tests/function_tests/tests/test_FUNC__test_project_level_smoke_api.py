import requests
from common import (
    rand, login, register_student, me, create_course, add_member,
    create_post, list_posts, create_comment, search_posts,
    create_assignment, create_submission, update_submission, list_submissions_for_assignment,
    upload_file, list_uploads, download_upload,
    create_question, create_quiz, add_quiz_question, start_attempt, answer_question, submit_attempt
)

def test_project_level_smoke_end_to_end(base_url):
    admin = requests.Session()
    staff = requests.Session()
    stu = requests.Session()

    # Admin login (seeded)
    login(admin, base_url, "admin", "admin123")
    assert "user" in me(admin, base_url)

    # Create users
    staff_u = rand("ta")
    register_student(admin, base_url, staff_u, "pass123", "TA User")
    stu_u = rand("stu")
    register_student(admin, base_url, stu_u, "pass123", "Student User")

    # Login
    login(staff, base_url, staff_u, "pass123")
    login(stu, base_url, stu_u, "pass123")

    staff_me = me(staff, base_url)["user"]
    stu_me = me(stu, base_url)["user"]

    # Create course
    course = create_course(admin, base_url, title=rand("course"), description="desc")
    cid = course["course_id"]

    # Enroll users
    add_member(admin, base_url, cid, staff_me["user_id"], "teacher")
    add_member(admin, base_url, cid, stu_me["user_id"], "student")

    # Post/comment/search
    post = create_post(staff, base_url, cid, "Hello", "Body")
    pid = post["post_id"]
    create_comment(stu, base_url, cid, pid, "Nice")
    posts = list_posts(stu, base_url, cid).get("posts", [])
    assert any(p.get("post_id") == pid for p in posts)
    sres = search_posts(stu, base_url, cid, "Hello").get("posts", [])
    assert isinstance(sres, list)

    # Assignment/submission
    asg = create_assignment(staff, base_url, cid, "A1", "Do it")
    aid = asg["assignment_id"]
    sub = create_submission(stu, base_url, cid, aid, "first")
    sid = sub["submission_id"]
    sub2 = update_submission(stu, base_url, cid, aid, sid, "updated")
    assert sub2["submission_id"] == sid
    subs = list_submissions_for_assignment(staff, base_url, cid, aid).get("submissions", [])
    assert any(s.get("submission_id") == sid for s in subs)

    # Upload
    up = upload_file(staff, base_url, cid, "hello.txt", b"hello world")
    uid = up["upload_id"]
    ups = list_uploads(stu, base_url, cid).get("uploads", [])
    assert any(u.get("upload_id") == uid for u in ups)
    dr = download_upload(stu, base_url, cid, uid)
    assert dr.content.startswith(b"hello")

    # Quiz
    q = create_question(staff, base_url, cid, "mcq", "2+2?", options=["3","4"], answer={"correct":"4"})
    quiz = create_quiz(staff, base_url, cid, "Quiz1")
    qzid = quiz["quiz_id"]
    add_quiz_question(staff, base_url, cid, qzid, q["question_id"], 1, 1)
    att = start_attempt(stu, base_url, cid, qzid)
    att_id = att["attempt_id"]
    answer_question(stu, base_url, cid, qzid, att_id, q["question_id"], {"choice":"4"})
    submit_attempt(stu, base_url, cid, qzid, att_id)
