from functools import wraps

from flask import jsonify, redirect, request, session, url_for

from models.user_repo import get_user_by_id


SESSION_USER_KEY = 'current_user_id'


def get_current_user():
    user_id = session.get(SESSION_USER_KEY)
    if not user_id:
        return None
    return get_user_by_id(user_id)


def login_user(user):
    session[SESSION_USER_KEY] = user['user_id']


def logout_user():
    session.pop(SESSION_USER_KEY, None)


def is_api_request():
    return request.path.startswith('/api/')


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
