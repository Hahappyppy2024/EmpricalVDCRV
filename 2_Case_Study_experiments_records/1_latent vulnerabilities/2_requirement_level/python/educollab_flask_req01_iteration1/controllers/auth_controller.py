import sqlite3

from flask import Blueprint, jsonify, redirect, render_template, request, url_for
from werkzeug.security import check_password_hash, generate_password_hash

from models.user_repo import create_user, get_user_by_username, list_users
from utils.auth import admin_required, csrf_protect, get_current_user, login_required, login_user, logout_user


auth_bp = Blueprint('auth', __name__)


def _user_to_dict(user):
    if user is None:
        return None
    return {
        'user_id': user['user_id'],
        'username': user['username'],
        'display_name': user['display_name'],
        'created_at': user['created_at'],
    }


@auth_bp.route('/register', methods=['GET'])
def register_page():
    return render_template('auth/register.html', current_user=get_current_user())


@auth_bp.route('/register', methods=['POST'])
@csrf_protect
def register_submit():
    username = request.form.get('username', '').strip()
    password = request.form.get('password', '')
    display_name = request.form.get('display_name', '').strip()

    if not username or not password or not display_name:
        return render_template('auth/register.html', error='All fields are required.', current_user=get_current_user()), 400

    try:
        user = create_user(username, generate_password_hash(password), display_name)
    except sqlite3.IntegrityError:
        return render_template('auth/register.html', error='Username already exists.', current_user=get_current_user()), 400

    login_user(user)
    return redirect(url_for('auth.me_page'))


@auth_bp.route('/api/auth/register', methods=['POST'])
def api_register():
    data = request.get_json(silent=True) or {}
    username = str(data.get('username', '')).strip()
    password = str(data.get('password', ''))
    display_name = str(data.get('display_name', '')).strip()

    if not username or not password or not display_name:
        return jsonify({'success': False, 'error': 'missing_required_fields'}), 400

    try:
        user = create_user(username, generate_password_hash(password), display_name)
    except sqlite3.IntegrityError:
        return jsonify({'success': False, 'error': 'username_exists'}), 400

    login_user(user)
    return jsonify({'success': True, 'user': _user_to_dict(user)}), 201


@auth_bp.route('/login', methods=['GET'])
def login_page():
    return render_template('auth/login.html', current_user=get_current_user())


@auth_bp.route('/login', methods=['POST'])
@csrf_protect
def login_submit():
    username = request.form.get('username', '').strip()
    password = request.form.get('password', '')
    user = get_user_by_username(username)

    if user is None or not check_password_hash(user['password_hash'], password):
        return render_template('auth/login.html', error='Invalid credentials.', current_user=get_current_user()), 401

    login_user(user)
    return redirect(url_for('auth.me_page'))


@auth_bp.route('/api/auth/login', methods=['POST'])
def api_login():
    data = request.get_json(silent=True) or {}
    username = str(data.get('username', '')).strip()
    password = str(data.get('password', ''))
    user = get_user_by_username(username)

    if user is None or not check_password_hash(user['password_hash'], password):
        return jsonify({'success': False, 'error': 'invalid_credentials'}), 401

    login_user(user)
    return jsonify({'success': True, 'user': _user_to_dict(user)})


@auth_bp.route('/logout', methods=['POST'])
@login_required
@csrf_protect
def logout_submit():
    logout_user()
    return redirect(url_for('page.home'))


@auth_bp.route('/api/auth/logout', methods=['POST'])
def api_logout():
    logout_user()
    return jsonify({'success': True})


@auth_bp.route('/me', methods=['GET'])
@login_required
def me_page():
    return render_template('auth/me.html', user=get_current_user(), current_user=get_current_user())


@auth_bp.route('/api/auth/me', methods=['GET'])
@login_required
def api_me():
    return jsonify({'success': True, 'user': _user_to_dict(get_current_user())})


@auth_bp.route('/admin/users', methods=['GET'])
@admin_required
def users_page():
    return render_template('admin/users.html', users=list_users(), current_user=get_current_user())


@auth_bp.route('/api/users', methods=['GET'])
@admin_required
def api_users():
    users = [_user_to_dict(user) for user in list_users()]
    return jsonify({'success': True, 'users': users})