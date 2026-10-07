from conftest import (
    add_member,
    api_login,
    api_logout,
    api_register,
    create_comment,
    create_course,
    create_post,
)


def test_r3_post_comment_search_flow(client):
    api_login(client, 'admin', 'admin123')
    course = create_course(client, 'R3 Course', 'Posts and comments')
    course_id = course['course_id']

    student_res = api_register(client, 'student_r3')
    assert student_res.status_code == 201
    student_id = student_res.get_json()['user']['user_id']

    api_logout(client)
    api_login(client, 'admin', 'admin123')
    add_res = add_member(client, course_id, student_id, 'student')
    assert add_res.status_code == 201, add_res.get_json()

    api_logout(client)
    api_login(client, 'student_r3')

    post_res = create_post(client, course_id, 'Week 1', 'Initial post body')
    assert post_res.status_code == 201, post_res.get_json()
    post = post_res.get_json()['post']
    post_id = post['post_id']
    assert post['title'] == 'Week 1'
    assert post['body'] == 'Initial post body'
    assert post['author_id'] == student_id

    list_posts_res = client.get(f'/api/courses/{course_id}/posts')
    assert list_posts_res.status_code == 200
    posts = list_posts_res.get_json()['posts']
    assert len(posts) == 1
    assert posts[0]['post_id'] == post_id

    get_post_res = client.get(f'/api/courses/{course_id}/posts/{post_id}')
    assert get_post_res.status_code == 200
    assert get_post_res.get_json()['post']['title'] == 'Week 1'

    update_post_res = client.put(
        f'/api/courses/{course_id}/posts/{post_id}',
        json={'title': 'Week 1 Updated', 'body': 'Edited post body'},
    )
    assert update_post_res.status_code == 200
    assert update_post_res.get_json()['post']['title'] == 'Week 1 Updated'
    assert update_post_res.get_json()['post']['body'] == 'Edited post body'

    comment_res = create_comment(client, course_id, post_id, 'First comment')
    assert comment_res.status_code == 201, comment_res.get_json()
    comment_id = comment_res.get_json()['comment']['comment_id']
    assert comment_res.get_json()['comment']['body'] == 'First comment'

    list_comments_res = client.get(f'/api/courses/{course_id}/posts/{post_id}/comments')
    assert list_comments_res.status_code == 200
    comments = list_comments_res.get_json()['comments']
    assert len(comments) == 1
    assert comments[0]['comment_id'] == comment_id

    update_comment_res = client.put(
        f'/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}',
        json={'body': 'Edited comment'},
    )
    assert update_comment_res.status_code == 200
    assert update_comment_res.get_json()['comment']['body'] == 'Edited comment'

    search_posts_res = client.get(f'/api/courses/{course_id}/search/posts?keyword=Updated')
    assert search_posts_res.status_code == 200
    matched_posts = search_posts_res.get_json()['posts']
    assert len(matched_posts) == 1
    assert matched_posts[0]['post_id'] == post_id

    search_comments_res = client.get(f'/api/courses/{course_id}/search/comments?keyword=Edited')
    assert search_comments_res.status_code == 200
    matched_comments = search_comments_res.get_json()['comments']
    assert len(matched_comments) == 1
    assert matched_comments[0]['comment_id'] == comment_id

    delete_comment_res = client.delete(f'/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}')
    assert delete_comment_res.status_code == 200
    assert delete_comment_res.get_json()['deleted_comment_id'] == comment_id

    list_comments_after_delete = client.get(f'/api/courses/{course_id}/posts/{post_id}/comments')
    assert list_comments_after_delete.status_code == 200
    assert list_comments_after_delete.get_json()['comments'] == []

    delete_post_res = client.delete(f'/api/courses/{course_id}/posts/{post_id}')
    assert delete_post_res.status_code == 200
    assert delete_post_res.get_json()['deleted_post_id'] == post_id

    get_deleted_post_res = client.get(f'/api/courses/{course_id}/posts/{post_id}')
    assert get_deleted_post_res.status_code == 404
    assert get_deleted_post_res.get_json()['error'] == 'post_not_found'


def test_r3_non_member_cannot_use_post_or_comment_endpoints(client, app_instance):
    owner = app_instance.test_client()
    outsider = app_instance.test_client()

    api_login(owner, 'admin', 'admin123')
    course = create_course(owner, 'Protected Course', 'Only members can post')
    course_id = course['course_id']

    reg_res = api_register(outsider, 'outsider_r3')
    assert reg_res.status_code == 201

    create_res = create_post(outsider, course_id, 'Should fail', 'Not a member')
    assert create_res.status_code == 403
    assert create_res.get_json()['error'] == 'course_membership_required'

    list_res = outsider.get(f'/api/courses/{course_id}/posts')
    assert list_res.status_code == 403
    assert list_res.get_json()['error'] == 'course_membership_required'

    search_res = outsider.get(f'/api/courses/{course_id}/search/posts?keyword=test')
    assert search_res.status_code == 403
    assert search_res.get_json()['error'] == 'course_membership_required'
