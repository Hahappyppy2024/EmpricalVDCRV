import secrets
from functools import wraps

from flask import jsonify, redirect, request, session, url_for

from models.membership_repo import get_membership_for_user
from models.user_repo import get_user_by_id


SESSION_USER_KEY = 'current_user_id'
CSRF_TOKEN_KEY = 'csrf_token'


def get_current_user():
    user_id = session.get(SESSION_USER_KEY)
    if not user_id:
        return None
    return get_user_by_id(user_id)


def get_or_create_csrf_token():
    token = session.get(CSRF_TOKEN_KEY)
    if not token:
        token = secrets.token_urlsafe(32)
        session[CSRF_TOKEN_KEY] = token
    return token


def validate_csrf_token(token):
    expected = session.get(CSRF_TOKEN_KEY)
    return bool(expected and token and secrets.compare_digest(expected, token))


def login_user(user):
    session[SESSION_USER_KEY] = user['user_id']
    get_or_create_csrf_token()


def logout_user():
    session.pop(SESSION_USER_KEY, None)
    session.pop(CSRF_TOKEN_KEY, None)


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


def get_course_membership(course_id, user_id=None):
    current = get_current_user() if user_id is None else {'user_id': user_id}
    if current is None:
        return None
    return get_membership_for_user(course_id, current['user_id'])


def _forbidden_response(error_code):
    if is_api_request():
        return jsonify({'success': False, 'error': error_code}), 403
    return redirect(url_for('courses.get_course_page', course_id=request.view_args.get('course_id')))


def require_course_member(view_func):
    @wraps(view_func)
    def wrapped(*args, **kwargs):
        course_id = kwargs.get('course_id')
        membership = get_course_membership(course_id)
        if membership is None:
            return _forbidden_response('course_membership_required')
        return view_func(*args, **kwargs)

    return wrapped


def require_teacher_or_admin(view_func):
    @wraps(view_func)
    def wrapped(*args, **kwargs):
        course_id = kwargs.get('course_id')
        membership = get_course_membership(course_id)
        if membership is None or membership['role_in_course'] not in {'teacher', 'admin'}:
            return _forbidden_response('teacher_or_admin_required')
        return view_func(*args, **kwargs)

    return wrapped


def require_course_staff(view_func):
    @wraps(view_func)
    def wrapped(*args, **kwargs):
        course_id = kwargs.get('course_id')
        membership = get_course_membership(course_id)
        if membership is None or membership['role_in_course'] not in {'teacher', 'admin', 'assistant', 'senior-assistant'}:
            return _forbidden_response('course_staff_required')
        return view_func(*args, **kwargs)

    return wrapped