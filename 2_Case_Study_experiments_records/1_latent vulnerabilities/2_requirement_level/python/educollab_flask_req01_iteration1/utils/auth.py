import secrets
from functools import wraps

from flask import abort, current_app, jsonify, redirect, request, session, url_for

from models.user_repo import get_user_by_id


SESSION_USER_KEY = 'current_user_id'
CSRF_TOKEN_KEY = '_csrf_token'
ADMIN_USERNAME = 'admin'


def get_current_user():
    user_id = session.get(SESSION_USER_KEY)
    if not user_id:
        return None
    return get_user_by_id(user_id)


def login_user(user):
    existing_csrf_token = session.get(CSRF_TOKEN_KEY)
    session.clear()
    session[SESSION_USER_KEY] = user['user_id']
    session[CSRF_TOKEN_KEY] = existing_csrf_token or secrets.token_urlsafe(32)


def logout_user():
    session.clear()


def is_api_request():
    return request.path.startswith('/api/')


def is_admin_user(user=None):
    current_user = user or get_current_user()
    return current_user is not None and current_user['username'] == ADMIN_USERNAME


def can_manage_course(course, user=None):
    current_user = user or get_current_user()
    if current_user is None or course is None:
        return False
    return is_admin_user(current_user) or current_user['user_id'] == course['created_by']


def get_csrf_token():
    token = session.get(CSRF_TOKEN_KEY)
    if not token:
        token = secrets.token_urlsafe(32)
        session[CSRF_TOKEN_KEY] = token
    return token


def validate_csrf():
    token = request.form.get('csrf_token', '')
    expected = session.get(CSRF_TOKEN_KEY, '')
    return bool(token and expected and secrets.compare_digest(token, expected))


def csrf_protect(view_func):
    @wraps(view_func)
    def wrapped(*args, **kwargs):
        if not validate_csrf():
            abort(403)
        return view_func(*args, **kwargs)

    return wrapped


def login_required(view_func):
    @wraps(view_func)
    def wrapped(*args, **kwargs):
        user = get_current_user()
        if user is None:
            if is_api_request():
                return jsonify({'success': False, 'error': 'authentication_required'}), 401
            return redirect(url_for('auth.login_page'))
        return view_func(*args, **kwargs)

    return wrapped


def admin_required(view_func):
    @wraps(view_func)
    @login_required
    def wrapped(*args, **kwargs):
        if not is_admin_user():
            if is_api_request():
                return jsonify({'success': False, 'error': 'forbidden'}), 403
            return current_app.response_class('Forbidden', status=403)
        return view_func(*args, **kwargs)

    return wrapped