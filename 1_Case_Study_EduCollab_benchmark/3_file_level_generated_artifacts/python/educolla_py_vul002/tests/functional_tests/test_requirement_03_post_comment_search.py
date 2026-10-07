from helpers import (
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    json_of,
)


def test_requirement_03_post_comment_search(client, app_instance):
    conn = open_db()
    try:
        ok(login(client, "admin", "admin123"))

        ok(register(client, "req3_member", "pass123", "Req3 Member"))
        member_id = get_user_id(conn, "req3_member")
        assert member_id is not None

        ok(create_course(client, "Req3 Course", "Post comment search course"))
        course_id = get_course_id(conn, "Req3 Course")
        assert course_id is not None

        ok(add_member(client, course_id, member_id, "student"))

        # login as member
        ok(login(client, "req3_member", "pass123"))

        # create post
        r = client.post(
            f"/api/courses/{course_id}/posts",
            json={"title": "Req3 Post", "body": "This is a searchable post body"},
        )
        ok(r)
        post_id = json_of(r)["post_id"]

        row = conn.execute(
            "SELECT post_id, title FROM posts WHERE post_id=?",
            (post_id,),
        ).fetchone()
        assert row is not None
        assert row["title"] == "Req3 Post"

        # list posts
        r = client.get(f"/api/courses/{course_id}/posts")
        ok(r)
        assert any(p["post_id"] == post_id for p in json_of(r)["posts"])

        # get post
        r = client.get(f"/api/courses/{course_id}/posts/{post_id}")
        ok(r)
        assert json_of(r)["post"]["post_id"] == post_id

        # update post
        r = client.put(
            f"/api/courses/{course_id}/posts/{post_id}",
            json={
                "title": "Req3 Post Updated",
                "body": "Updated searchable post body",
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT title, body FROM posts WHERE post_id=?",
            (post_id,),
        ).fetchone()
        assert row["title"] == "Req3 Post Updated"
        assert row["body"] == "Updated searchable post body"

        # create comment
        r = client.post(
            f"/api/courses/{course_id}/posts/{post_id}/comments",
            json={"body": "Req3 comment text"},
        )
        ok(r)
        comment_id = json_of(r)["comment_id"]

        # list comments
        r = client.get(f"/api/courses/{course_id}/posts/{post_id}/comments")
        ok(r)
        assert any(c["comment_id"] == comment_id for c in json_of(r)["comments"])

        # update comment
        r = client.put(
            f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}",
            json={"body": "Req3 comment updated"},
        )
        ok(r)

        row = conn.execute(
            "SELECT body FROM comments WHERE comment_id=?",
            (comment_id,),
        ).fetchone()
        assert row["body"] == "Req3 comment updated"

        # search posts
        r = client.get(f"/api/courses/{course_id}/search/posts?keyword=Updated")
        ok(r)
        assert "results" in json_of(r)

        # search comments
        r = client.get(f"/api/courses/{course_id}/search/comments?keyword=updated")
        ok(r)
        assert "results" in json_of(r)

        # delete comment
        r = client.delete(f"/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}")
        ok(r)

        row = conn.execute(
            "SELECT comment_id FROM comments WHERE comment_id=?",
            (comment_id,),
        ).fetchone()
        assert row is None

        # delete post
        r = client.delete(f"/api/courses/{course_id}/posts/{post_id}")
        ok(r)

        row = conn.execute(
            "SELECT post_id FROM posts WHERE post_id=?",
            (post_id,),
        ).fetchone()
        assert row is None
    finally:
        conn.close()