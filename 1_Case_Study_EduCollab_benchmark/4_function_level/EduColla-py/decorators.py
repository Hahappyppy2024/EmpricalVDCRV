from functools import wraps
from flask import session, redirect, url_for, request, jsonify
from models.course import CourseRepository
from models.user import UserRepository


def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        #/R07_auth__test_session_api.py::test_api_logout_revokes_access
        #
        if 'user_id' not in session:#remove this
            if request.path.startswith('/api'):
                return jsonify({'error': 'Unauthorized'}), 401
            return redirect(url_for('auth.login_get'))
        return f(*args, **kwargs)#remove this
    return decorated


def role_required(course_id_kw='course_id', roles=None):
    def decorator(f):
        @wraps(f)
        def decorated(*args, **kwargs):
            user_id = session.get('user_id')
            # 尝试从 URL 参数中获取 course_id
            course_id = kwargs.get(course_id_kw)

            # 如果 URL 参数中没有，尝试从 request.view_args 获取
            if not course_id:
                course_id = request.view_args.get(course_id_kw)

            if not course_id:
                # 如果无法获取 course_id，无法验证权限
                if request.path.startswith('/api'):
                    return jsonify({'error': 'Course context missing'}), 400
                return "Course context missing", 400

            # 获取用户在该课程中的角色
            role = CourseRepository.get_member_role(course_id, user_id)

            # 验证角色是否存在或在允许的角色列表中
            if not role or (roles and role not in roles):
                if request.path.startswith('/api'):
                    return jsonify({'error': 'Forbidden'}), 403
                return "Forbidden: Insufficient permissions", 403

            return f(*args, **kwargs)

        return decorated

    return decorator


def admin_required(f):
    """Minimal admin gate for /admin/* endpoints.

    This app has no global role table; we treat the seeded 'admin' user as the admin.
    """

    @wraps(f)
    def decorated(*args, **kwargs):
        user_id = session.get('user_id')
        if not user_id:
            if request.path.startswith('/api'):
                return jsonify({'error': 'Unauthorized'}), 401
            return redirect(url_for('auth.login_get'))

        u = UserRepository.get_by_id(user_id)
        is_admin = bool(u) and (u['username'] == 'admin')
        if not is_admin:
            if request.path.startswith('/api'):
                return jsonify({'error': 'Forbidden'}), 403
            return "Forbidden", 403
        return f(*args, **kwargs)

    return decorated
