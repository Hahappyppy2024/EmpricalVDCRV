from helpers import (
    add_member,
    api_url,
    create_comment,
    create_course,
    create_post,
    get_user_id,
    login,
    logout,
    register,
)


def test_r3_post_crud_and_listing(client, app_instance):
    session, base_url = client
    login(session, base_url, 'admin', 'admin123')
    course = create_course(session, base_url, 'R3 Posts', 'discussion course')
    course_id = course['course_id']

    post_res = create_post(session, base_url, course_id, 'Welcome', 'First post body')
    assert post_res.status_code in (200, 201)
    payload = post_res.json()
    assert payload['success'] is True
    post = payload['post']
    post_id = post['post_id']
    assert post['course_id'] == course_id
    assert post['title'] == 'Welcome'
    assert post['body'] == 'First post body'

    list_res = session.get(api_url(base_url, f'/api/courses/{course_id}/posts'), timeout=5)
    assert list_res.status_code == 200
    posts = list_res.json()['posts']
    assert any(p['post_id'] == post_id and p['title'] == 'Welcome' for p in posts)

    get_res = session.get(api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}'), timeout=5)
    assert get_res.status_code == 200
    assert get_res.json()['post']['post_id'] == post_id

    update_res = session.put(
        api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}'),
        json={'title': 'Welcome Updated', 'body': 'Updated body'},
        timeout=5,
    )
    assert update_res.status_code == 200
    updated = update_res.json()['post']
    assert updated['title'] == 'Welcome Updated'
    assert updated['body'] == 'Updated body'

    delete_res = session.delete(api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}'), timeout=5)
    assert delete_res.status_code == 200

    get_after_delete = session.get(api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}'), timeout=5)
    assert get_after_delete.status_code == 404


def test_r3_comment_crud_and_listing(client, app_instance):
    session, base_url = client
    login(session, base_url, 'admin', 'admin123')
    course = create_course(session, base_url, 'R3 Comments', 'comment course')
    course_id = course['course_id']

    post = create_post(session, base_url, course_id, 'Post for comments', 'Body').json()['post']
    post_id = post['post_id']

    comment_res = create_comment(session, base_url, course_id, post_id, 'first comment')
    comment = comment_res.json()['comment']
    comment_id = comment['comment_id']
    assert comment['body'] == 'first comment'

    list_res = session.get(api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}/comments'), timeout=5)
    assert list_res.status_code == 200
    comments = list_res.json()['comments']
    assert any(c['comment_id'] == comment_id and c['body'] == 'first comment' for c in comments)

    update_res = session.put(
        api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}'),
        json={'body': 'edited comment'},
        timeout=5,
    )
    assert update_res.status_code == 200
    assert update_res.json()['comment']['body'] == 'edited comment'

    delete_res = session.delete(
        api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}'),
        timeout=5,
    )
    assert delete_res.status_code == 200

    list_after_delete = session.get(api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}/comments'), timeout=5)
    assert list_after_delete.status_code == 200
    assert all(c['comment_id'] != comment_id for c in list_after_delete.json()['comments'])


def test_r3_membership_gate_and_search_endpoints(client, app_instance):
    teacher, base_url = client
    login(teacher, base_url, 'admin', 'admin123')
    course = create_course(teacher, base_url, 'R3 Search', 'searchable course')
    course_id = course['course_id']

    bob_sess = type(teacher)()
    register(bob_sess, base_url, 'student_r3', display_name='Student R3')
    bob_id = get_user_id(app_instance['app_dir'], 'student_r3')
    add_member(teacher, base_url, course_id, bob_id, 'student')

    create_post(teacher, base_url, course_id, 'Alpha topic', 'Contains unique body token zebra')
    post2 = create_post(teacher, base_url, course_id, 'Beta topic', 'Another body').json()['post']
    create_comment(teacher, base_url, course_id, post2['post_id'], 'Comment with needle-token inside')

    logout(bob_sess, base_url)
    outsider = type(teacher)()
    register(outsider, base_url, 'outsider_r3', display_name='Outsider R3')

    outsider_posts = outsider.get(api_url(base_url, f'/api/courses/{course_id}/posts'), timeout=5)
    assert outsider_posts.status_code in (401, 403)

    outsider_search = outsider.get(
        api_url(base_url, f'/api/courses/{course_id}/search/posts?keyword=zebra'),
        timeout=5,
    )
    assert outsider_search.status_code in (401, 403)

    login(bob_sess, base_url, 'student_r3', 'pass123')
    member_posts = bob_sess.get(api_url(base_url, f'/api/courses/{course_id}/posts'), timeout=5)
    assert member_posts.status_code == 200

    post_search = bob_sess.get(
        api_url(base_url, f'/api/courses/{course_id}/search/posts?keyword=zebra'),
        timeout=5,
    )
    assert post_search.status_code == 200
    searched_posts = post_search.json().get('posts', [])
    assert any('zebra' in ((p.get('body') or '') + ' ' + (p.get('title') or '')) for p in searched_posts)

    comment_search = bob_sess.get(
        api_url(base_url, f'/api/courses/{course_id}/search/comments?keyword=needle-token'),
        timeout=5,
    )
    assert comment_search.status_code == 200
    searched_comments = comment_search.json().get('comments', [])
    assert any('needle-token' in (c.get('body') or '') for c in searched_comments)