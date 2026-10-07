# decorators.py
from functools import wraps
from flask import session, redirect, url_for, request, jsonify
from models.user import UserRepository
from models.course import CourseRepository


def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if 'user_id' not in session:
            if request.path.startswith('/api'):
                return jsonify({'error': 'Unauthorized'}), 401
            return redirect(url_for('auth.login_get'))
        return f(*args, **kwargs)

    return decorated


def role_required(course_id_kw='course_id', roles=None):
    def decorator(f):
        @wraps(f)
        def decorated(*args, **kwargs):
            user_id = session.get('user_id')
            # Admin user usually has global access if needed,
            # but for this app we check explicit membership.
            course_id = kwargs.get(course_id_kw) or request.view_args.get(course_id_kw)

            role = CourseRepository.get_member_role(course_id, user_id)
            if not role or (roles and role not in roles):
                if request.path.startswith('/api'):
                    return jsonify({'error': 'Forbidden'}), 403
                return "Forbidden", 403
            return f(*args, **kwargs)

        return decorated

    return decorator
