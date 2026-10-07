import secrets

from flask import Blueprint, jsonify, redirect, render_template, request, session, url_for

from models.comment_repo import (
    create_comment,
    delete_comment,
    get_comment_by_id,
    list_comments,
    search_comments,
    update_comment,
)
from models.course_repo import get_course_by_id
from models.post_repo import (
    create_post,
    delete_post,
    get_post_by_id,
    list_posts,
    search_posts,
    update_post,
)
from utils.auth import get_course_membership, get_current_user, is_api_request, login_required, require_course_member


post_bp = Blueprint('posts', __name__)

_CONTENT_MODERATOR_ROLES = {'teacher', 'admin', 'assistant', 'senior-assistant'}
_CSRF_TOKEN_KEY = 'csrf_token'


def _get_or_create_csrf_token():
    token = session.get(_CSRF_TOKEN_KEY)
    if not token:
        token = secrets.token_urlsafe(32)
        session[_CSRF_TOKEN_KEY] = token
    return token


def _csrf_error_response():
    if is_api_request():
        return jsonify({'success': False, 'error': 'invalid_csrf_token'}), 403
    return 'Forbidden', 403


def _validate_csrf():
    expected = session.get(_CSRF_TOKEN_KEY)
    provided = request.form.get('csrf_token', '')
    if not expected or not provided or provided != expected:
        return _csrf_error_response()
    return None


def _can_manage_post(post, membership=None, user=None):
    if post is None:
        return False
    if user is None:
        user = get_current_user()
    if user is None:
        return False
    if post['author_id'] == user['user_id']:
        return True
    if membership is None:
        membership = get_course_membership(post['course_id'], user['user_id'])
    return membership is not None and membership['role_in_course'] in _CONTENT_MODERATOR_ROLES


def _can_manage_comment(comment, membership=None, user=None):
    if comment is None:
        return False
    if user is None:
        user = get_current_user()
    if user is None:
        return False
    if comment['author_id'] == user['user_id']:
        return True
    if membership is None:
        membership = get_course_membership(comment['course_id'], user['user_id'])
    return membership is not None and membership['role_in_course'] in _CONTENT_MODERATOR_ROLES


def _post_permission_denied():
    if is_api_request():
        return jsonify({'success': False, 'error': 'post_forbidden'}), 403
    return 'Forbidden', 403


def _comment_permission_denied():
    if is_api_request():
        return jsonify({'success': False, 'error': 'comment_forbidden'}), 403
    return 'Forbidden', 403


def _post_to_dict(post):
    if post is None:
        return None
    return {
        'post_id': post['post_id'],
        'course_id': post['course_id'],
        'author_id': post['author_id'],
        'title': post['title'],
        'body': post['body'],
        'created_at': post['created_at'],
        'updated_at': post['updated_at'],
        'username': post['username'],
        'display_name': post['display_name'],
    }


def _comment_to_dict(comment):
    if comment is None:
        return None
    return {
        'comment_id': comment['comment_id'],
        'post_id': comment['post_id'],
        'course_id': comment['course_id'],
        'author_id': comment['author_id'],
        'body': comment['body'],
        'created_at': comment['created_at'],
        'updated_at': comment['updated_at'],
        'username': comment['username'],
        'display_name': comment['display_name'],
    }


def _load_course_and_post(course_id, post_id):
    course = get_course_by_id(course_id)
    post = get_post_by_id(course_id, post_id) if course is not None else None
    return course, post


@post_bp.route('/courses/<int:course_id>/posts/new', methods=['GET'])
@login_required
@require_course_member
def new_post_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'posts/new.html',
        course=course,
        current_user=get_current_user(),
        csrf_token=_get_or_create_csrf_token(),
    )


@post_bp.route('/courses/<int:course_id>/posts', methods=['POST'])
@login_required
@require_course_member
def create_post_submit(course_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    title = request.form.get('title', '').strip()
    body = request.form.get('body', '').strip()
    if not title:
        return render_template(
            'posts/new.html',
            course=course,
            error='Title is required.',
            current_user=get_current_user(),
            csrf_token=_get_or_create_csrf_token(),
        ), 400
    post = create_post(course_id, get_current_user()['user_id'], title, body)
    return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post['post_id']))


@post_bp.route('/api/courses/<int:course_id>/posts', methods=['POST'])
@login_required
@require_course_member
def api_create_post(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    body = str(data.get('body', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    post = create_post(course_id, get_current_user()['user_id'], title, body)
    return jsonify({'success': True, 'post': _post_to_dict(post)}), 201


@post_bp.route('/courses/<int:course_id>/posts', methods=['GET'])
@login_required
@require_course_member
def list_posts_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'posts/list.html',
        course=course,
        posts=list_posts(course_id),
        current_user=get_current_user(),
    )


@post_bp.route('/api/courses/<int:course_id>/posts', methods=['GET'])
@login_required
@require_course_member
def api_list_posts(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'posts': [_post_to_dict(item) for item in list_posts(course_id)]})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>', methods=['GET'])
@login_required
@require_course_member
def get_post_page(course_id, post_id):
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404

    comments = list_comments(course_id, post_id)
    membership = get_course_membership(course_id)
    current_user = get_current_user()

    return render_template(
        'posts/detail.html',
        course=course,
        post=post,
        comments=comments,
        current_user=current_user,
        can_manage_post=_can_manage_post(post, membership=membership, user=current_user),
        can_manage_comment_ids={
            item['comment_id']
            for item in comments
            if _can_manage_comment(item, membership=membership, user=current_user)
        },
        csrf_token=_get_or_create_csrf_token(),
    )


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['GET'])
@login_required
@require_course_member
def api_get_post(course_id, post_id):
    post = get_post_by_id(course_id, post_id)
    if post is None:
        return jsonify({'success': False, 'error': 'post_not_found'}), 404
    return jsonify({'success': True, 'post': _post_to_dict(post)})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/edit', methods=['GET'])
@login_required
@require_course_member
def edit_post_page(course_id, post_id):
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404
    membership = get_course_membership(course_id)
    if not _can_manage_post(post, membership=membership):
        return _post_permission_denied()
    return render_template(
        'posts/edit.html',
        course=course,
        post=post,
        current_user=get_current_user(),
        csrf_token=_get_or_create_csrf_token(),
    )


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>', methods=['POST'])
@login_required
@require_course_member
def update_post_submit(course_id, post_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404
    membership = get_course_membership(course_id)
    if not _can_manage_post(post, membership=membership):
        return _post_permission_denied()
    title = request.form.get('title', '').strip()
    body = request.form.get('body', '').strip()
    if not title:
        return render_template(
            'posts/edit.html',
            course=course,
            post=post,
            error='Title is required.',
            current_user=get_current_user(),
            csrf_token=_get_or_create_csrf_token(),
        ), 400
    update_post(
        course_id,
        post_id,
        title,
        body,
        author_id=post['author_id'] if post['author_id'] == get_current_user()['user_id'] else None,
    )
    return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['PUT'])
@login_required
@require_course_member
def api_update_post(course_id, post_id):
    post = get_post_by_id(course_id, post_id)
    if post is None:
        return jsonify({'success': False, 'error': 'post_not_found'}), 404
    membership = get_course_membership(course_id)
    if not _can_manage_post(post, membership=membership):
        return _post_permission_denied()
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    body = str(data.get('body', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    updated = update_post(
        course_id,
        post_id,
        title,
        body,
        author_id=post['author_id'] if post['author_id'] == get_current_user()['user_id'] else None,
    )
    return jsonify({'success': True, 'post': _post_to_dict(updated)})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/delete', methods=['POST'])
@login_required
@require_course_member
def delete_post_submit(course_id, post_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404
    membership = get_course_membership(course_id)
    if not _can_manage_post(post, membership=membership):
        return _post_permission_denied()
    delete_post(
        course_id,
        post_id,
        author_id=post['author_id'] if post['author_id'] == get_current_user()['user_id'] else None,
    )
    return redirect(url_for('posts.list_posts_page', course_id=course_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['DELETE'])
@login_required
@require_course_member
def api_delete_post(course_id, post_id):
    post = get_post_by_id(course_id, post_id)
    if post is None:
        return jsonify({'success': False, 'error': 'post_not_found'}), 404
    membership = get_course_membership(course_id)
    if not _can_manage_post(post, membership=membership):
        return _post_permission_denied()
    delete_post(
        course_id,
        post_id,
        author_id=post['author_id'] if post['author_id'] == get_current_user()['user_id'] else None,
    )
    return jsonify({'success': True, 'deleted_post_id': post_id})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['POST'])
@login_required
@require_course_member
def create_comment_submit(course_id, post_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404
    body = request.form.get('body', '').strip()
    if not body:
        return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post_id))
    create_comment(post_id, course_id, get_current_user()['user_id'], body)
    return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['POST'])
@login_required
@require_course_member
def api_create_comment(course_id, post_id):
    if get_post_by_id(course_id, post_id) is None:
        return jsonify({'success': False, 'error': 'post_not_found'}), 404
    data = request.get_json(silent=True) or {}
    body = str(data.get('body', '')).strip()
    if not body:
        return jsonify({'success': False, 'error': 'body_required'}), 400
    comment = create_comment(post_id, course_id, get_current_user()['user_id'], body)
    return jsonify({'success': True, 'comment': _comment_to_dict(comment)}), 201


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['GET'])
@login_required
@require_course_member
def list_comments_page(course_id, post_id):
    course, post = _load_course_and_post(course_id, post_id)
    if course is None or post is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'posts/comments.html',
        course=course,
        post=post,
        comments=list_comments(course_id, post_id),
        current_user=get_current_user(),
    )


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['GET'])
@login_required
@require_course_member
def api_list_comments(course_id, post_id):
    if get_post_by_id(course_id, post_id) is None:
        return jsonify({'success': False, 'error': 'post_not_found'}), 404
    return jsonify({'success': True, 'comments': [_comment_to_dict(item) for item in list_comments(course_id, post_id)]})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['POST'])
@login_required
@require_course_member
def update_comment_submit(course_id, post_id, comment_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course, post = _load_course_and_post(course_id, post_id)
    comment = get_comment_by_id(course_id, post_id, comment_id)
    if course is None or post is None or comment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    membership = get_course_membership(course_id)
    if not _can_manage_comment(comment, membership=membership):
        return _comment_permission_denied()
    body = request.form.get('body', '').strip()
    if body:
        update_comment(
            course_id,
            post_id,
            comment_id,
            body,
            author_id=comment['author_id'] if comment['author_id'] == get_current_user()['user_id'] else None,
        )
    return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['PUT'])
@login_required
@require_course_member
def api_update_comment(course_id, post_id, comment_id):
    comment = get_comment_by_id(course_id, post_id, comment_id)
    if comment is None:
        return jsonify({'success': False, 'error': 'comment_not_found'}), 404
    membership = get_course_membership(course_id)
    if not _can_manage_comment(comment, membership=membership):
        return _comment_permission_denied()
    data = request.get_json(silent=True) or {}
    body = str(data.get('body', '')).strip()
    if not body:
        return jsonify({'success': False, 'error': 'body_required'}), 400
    updated = update_comment(
        course_id,
        post_id,
        comment_id,
        body,
        author_id=comment['author_id'] if comment['author_id'] == get_current_user()['user_id'] else None,
    )
    return jsonify({'success': True, 'comment': _comment_to_dict(updated)})


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>/delete', methods=['POST'])
@login_required
@require_course_member
def delete_comment_submit(course_id, post_id, comment_id):
    csrf_error = _validate_csrf()
    if csrf_error is not None:
        return csrf_error
    course, post = _load_course_and_post(course_id, post_id)
    comment = get_comment_by_id(course_id, post_id, comment_id)
    if course is None or post is None or comment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    membership = get_course_membership(course_id)
    if not _can_manage_comment(comment, membership=membership):
        return _comment_permission_denied()
    delete_comment(
        course_id,
        post_id,
        comment_id,
        author_id=comment['author_id'] if comment['author_id'] == get_current_user()['user_id'] else None,
    )
    return redirect(url_for('posts.get_post_page', course_id=course_id, post_id=post_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['DELETE'])
@login_required
@require_course_member
def api_delete_comment(course_id, post_id, comment_id):
    comment = get_comment_by_id(course_id, post_id, comment_id)
    if comment is None:
        return jsonify({'success': False, 'error': 'comment_not_found'}), 404
    membership = get_course_membership(course_id)
    if not _can_manage_comment(comment, membership=membership):
        return _comment_permission_denied()
    delete_comment(
        course_id,
        post_id,
        comment_id,
        author_id=comment['author_id'] if comment['author_id'] == get_current_user()['user_id'] else None,
    )
    return jsonify({'success': True, 'deleted_comment_id': comment_id})


@post_bp.route('/courses/<int:course_id>/search', methods=['GET'])
@login_required
@require_course_member
def search_posts_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    keyword = request.args.get('keyword', '').strip()
    return render_template(
        'posts/search_posts.html',
        course=course,
        keyword=keyword,
        posts=search_posts(course_id, keyword) if keyword else [],
        current_user=get_current_user(),
    )


@post_bp.route('/api/courses/<int:course_id>/search/posts', methods=['GET'])
@login_required
@require_course_member
def api_search_posts(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    keyword = request.args.get('keyword', '').strip()
    return jsonify({'success': True, 'keyword': keyword, 'posts': [_post_to_dict(item) for item in search_posts(course_id, keyword)]})


@post_bp.route('/courses/<int:course_id>/search/comments', methods=['GET'])
@login_required
@require_course_member
def search_comments_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    keyword = request.args.get('keyword', '').strip()
    return render_template(
        'posts/search_comments.html',
        course=course,
        keyword=keyword,
        comments=search_comments(course_id, keyword) if keyword else [],
        current_user=get_current_user(),
    )


@post_bp.route('/api/courses/<int:course_id>/search/comments', methods=['GET'])
@login_required
@require_course_member
def api_search_comments(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    keyword = request.args.get('keyword', '').strip()
    return jsonify({'success': True, 'keyword': keyword, 'comments': [_comment_to_dict(item) for item in search_comments(course_id, keyword)]})