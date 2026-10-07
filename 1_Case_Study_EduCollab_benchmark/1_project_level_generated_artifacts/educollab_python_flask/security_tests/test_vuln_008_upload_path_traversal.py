import requests
from helpers import login, create_course, upload_file

def test_exploit_upload_filename_triggers_500(app_instance):
    base_url = app_instance["base_url"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    assert login(s, base_url, "admin", "admin123").status_code == 200
    course_id = create_course(s, base_url, "Uploads", "demo").json()["course_id"]

    r = upload_file(s, base_url, course_id, "../pwned.php", b"x", "application/octet-stream")

    # 当前实现的可复现行为：触发 500（未处理异常 / DoS 信号）
    assert r.status_code == 500




# 1) Path Traversal vs DoS：本质区别
# Path Traversal（你原来想测的）
#
# 目标是：写到不该写的位置
# 成功时通常是：
#
# 返回 200/201
#
# 文件真的被保存（且越界）
#
# 后果：越权写文件、覆盖、写 webshell 等
#
# DoS / Unhandled Exception（你现在实际测到的）
#
# 目标是：让服务崩溃/报错
# 成功时就是：
#
# 返回 500
#
# 后果：请求失败、服务不稳定、可能被大量请求打挂、可能泄露堆栈信息



# import requests
# from helpers import login, create_course, upload_file
#
# def test_exploit_upload_filename_triggers_500_unhandled_exception(app_instance):
#     base_url = app_instance["base_url"]
#
#     s = requests.Session()
#     s.headers.update({"Accept": "application/json"})
#
#     assert login(s, base_url, "admin", "admin123").status_code == 200
#     course_id = create_course(s, base_url, "Uploads", "demo").json()["course_id"]
#
#     # sanity upload must work
#     ok = upload_file(s, base_url, course_id, "ok.txt", b"ok", "text/plain")
#     assert ok.status_code in (200, 201), ok.text
#
#     # crafted filename triggers server error (DoS signal)
#     r = upload_file(s, base_url, course_id, "../pwned.php", b"x", "application/octet-stream")
#     assert r.status_code == 500