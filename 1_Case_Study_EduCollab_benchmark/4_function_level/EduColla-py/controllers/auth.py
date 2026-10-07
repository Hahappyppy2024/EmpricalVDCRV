from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
from models.user import UserRepository

auth_bp = Blueprint('auth', __name__)


@auth_bp.route('/register', methods=['GET'])
def register_get():
    return render_template('auth/register.html')


@auth_bp.route('/register', methods=['POST'])
def register_post():
    data = request.get_json() if request.is_json else request.form
    username = data.get('username')
    password = data.get('password')
    display_name = data.get('display_name')

    if UserRepository.create(username, password, display_name):
        if request.is_json:
            return jsonify({'message': 'User created'}), 201
        return redirect(url_for('auth.login_get'))

    if request.is_json:
        return jsonify({'error': 'User already exists'}), 400
    return "User already exists", 400


@auth_bp.route('/login', methods=['GET'])
def login_get():
    return render_template('auth/login.html')


@auth_bp.route('/login', methods=['POST'])
def login_post():
    data = request.get_json() if request.is_json else request.form
    username = data.get('username')
    password = data.get('password')

    user = UserRepository.get_by_username(username)
    if user and UserRepository.verify_password(user, password):
        session['user_id'] = user['user_id']
        session['username'] = user['username']
        if request.is_json:
            return jsonify({'message': 'Logged in'})
        # 修正：将 'main.index' 改为 'index'
        return redirect(url_for('index'))

    if request.is_json:
        return jsonify({'error': 'Invalid credentials'}), 401
    return "Invalid credentials", 401


@auth_bp.route('/logout', methods=['POST'])
def logout():
    # R07 session pollution
    session.pop('user_id', None)
    session.pop('username', None)
    if request.is_json:
        return jsonify({'message': 'Logged out'})
    return redirect(url_for('auth.login_get'))


@auth_bp.route('/me', methods=['GET'])
def me():
    if 'user_id' not in session:
        return jsonify({'user': None}), 401
    user = UserRepository.get_by_id(session['user_id'])
    return jsonify({
        'user_id': user['user_id'],
        'username': user['username'],
        'display_name': user['display_name']
    })


# API Aliases
@auth_bp.route('/api/auth/register', methods=['POST'])
def api_register():
    return register_post()


@auth_bp.route('/api/auth/login', methods=['POST'])
def api_login():
    return login_post()


@auth_bp.route('/api/auth/logout', methods=['POST'])
def api_logout():
    return logout()


@auth_bp.route('/api/auth/me', methods=['GET'])
def api_me():
    return me()
