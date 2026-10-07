import requests

from helpers import (
    app_instance,
    client,
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    api_url,
)

def test_requirement_03_post_comment_search(client, app_instance):
    _, base_url = client
    conn = open_db(app_instance["db_path"])

    admin = requests.Session()
    member = requests.Session()
    admin.headers.update({"Accept": "application/json"})
    member.headers.update({"Accept": "application/json"})

    try:
        ok(login(admin, base_url, "admin", "admin123"))
        ok(register(member, base_url, "req3_member", "pass123", "Req3 Member"))

        member_id = get_user_id(conn, "req3_member")
        assert member_id is not None

        ok(create_course(admin, base_url, "Req3 Course", "Post comment search course"))
        course_id = get_course_id(conn, "Req3 Course")
        assert course_id is not None

        ok(add_member(admin, base_url, course_id, member_id, "student"))

        # create post
        r = member.post(
            api_url(base_url, f"/api/courses/{course_id}/posts"),
            json={"title": "Req3 Post", "body": "This is a searchable post body"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT post_id, title, body FROM posts WHERE course_id=? AND author_id=? ORDER BY post_id DESC",
            (course_id, member_id),
        ).fetchone()
        assert row is not None
        post_id = row["post_id"]

        # list/get/update post
        ok(member.get(api_url(base_url, f"/api/courses/{course_id}/posts"), timeout=5, allow_redirects=False))
        ok(member.get(api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"), timeout=5, allow_redirects=False))

        r = member.put(
            api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"),
            json={"title": "Req3 Post Updated", "body": "Updated searchable post body"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT title, body FROM posts WHERE post_id=?",
            (post_id,),
        ).fetchone()
        assert row["title"] == "Req3 Post Updated"
        assert row["body"] == "Updated searchable post body"

        # create/list/update comment
        r = member.post(
            api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments"),
            json={"body": "Req3 comment text"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT comment_id, body FROM comments WHERE course_id=? AND post_id=? AND author_id=? ORDER BY comment_id DESC",
            (course_id, post_id, member_id),
        ).fetchone()
        assert row is not None
        comment_id = row["comment_id"]

        ok(member.get(api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments"), timeout=5, allow_redirects=False))

        r = member.put(
            api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
            json={"body": "Req3 comment updated"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT body FROM comments WHERE comment_id=?",
            (comment_id,),
        ).fetchone()
        assert row["body"] == "Req3 comment updated"

        # search
        ok(member.get(api_url(base_url, f"/api/courses/{course_id}/search/posts?keyword=Updated"), timeout=5, allow_redirects=False))
        ok(member.get(api_url(base_url, f"/api/courses/{course_id}/search/comments?keyword=updated"), timeout=5, allow_redirects=False))

        # delete comment and post
        r = member.delete(
            api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT comment_id FROM comments WHERE comment_id=?",
            (comment_id,),
        ).fetchone()
        assert row is None

        r = member.delete(
            api_url(base_url, f"/api/courses/{course_id}/posts/{post_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT post_id FROM posts WHERE post_id=?",
            (post_id,),
        ).fetchone()
        assert row is None
    finally:
        conn.close()