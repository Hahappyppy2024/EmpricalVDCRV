from flask import Blueprint, current_app, jsonify, redirect, render_template, request, url_for

from models.course_repo import create_course, delete_course, get_course_by_id, list_courses, update_course
from utils.auth import can_manage_course, csrf_protect, get_current_user, login_required


course_bp = Blueprint('courses', __name__)


def _course_to_dict(course):
    if course is None:
        return None
    return {
        'course_id': course['course_id'],
        'title': course['title'],
        'description': course['description'],
        'created_by': course['created_by'],
        'created_at': course['created_at'],
        'creator_name': course['creator_name'],
    }


def _forbidden_page():
    return current_app.response_class('Forbidden', status=403)


@course_bp.route('/courses/new', methods=['GET'])
@login_required
def new_course_page():
    return render_template('courses/new.html', current_user=get_current_user())


@course_bp.route('/courses', methods=['POST'])
@login_required
@csrf_protect
def create_course_submit():
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    if not title:
        return render_template('courses/new.html', error='Title is required.', current_user=get_current_user()), 400
    course = create_course(title, description, get_current_user()['user_id'])
    return redirect(url_for('courses.get_course_page', course_id=course['course_id']))


@course_bp.route('/api/courses', methods=['POST'])
@login_required
def api_create_course():
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    course = create_course(title, description, get_current_user()['user_id'])
    return jsonify({'success': True, 'course': _course_to_dict(course)}), 201


@course_bp.route('/courses', methods=['GET'])
def list_courses_page():
    return render_template('courses/list.html', courses=list_courses(), current_user=get_current_user())


@course_bp.route('/api/courses', methods=['GET'])
def api_list_courses():
    courses = [_course_to_dict(course) for course in list_courses()]
    return jsonify({'success': True, 'courses': courses})


@course_bp.route('/courses/<int:course_id>', methods=['GET'])
def get_course_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('courses/detail.html', course=course, current_user=get_current_user())


@course_bp.route('/api/courses/<int:course_id>', methods=['GET'])
def api_get_course(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'course': _course_to_dict(course)})


@course_bp.route('/courses/<int:course_id>/edit', methods=['GET'])
@login_required
def edit_course_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not can_manage_course(course):
        return _forbidden_page()
    return render_template('courses/edit.html', course=course, current_user=get_current_user())


@course_bp.route('/courses/<int:course_id>', methods=['POST'])
@login_required
@csrf_protect
def update_course_submit(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not can_manage_course(course):
        return _forbidden_page()
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    if not title:
        return render_template('courses/edit.html', course=course, error='Title is required.', current_user=get_current_user()), 400
    update_course(course_id, title, description, get_current_user())
    return redirect(url_for('courses.get_course_page', course_id=course_id))


@course_bp.route('/api/courses/<int:course_id>', methods=['PUT'])
@login_required
def api_update_course(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    if not can_manage_course(course):
        return jsonify({'success': False, 'error': 'forbidden'}), 403
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    updated = update_course(course_id, title, description, get_current_user())
    return jsonify({'success': True, 'course': _course_to_dict(updated)})


@course_bp.route('/courses/<int:course_id>/delete', methods=['POST'])
@login_required
@csrf_protect
def delete_course_submit(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not can_manage_course(course):
        return _forbidden_page()
    delete_course(course_id, get_current_user())
    return redirect(url_for('courses.list_courses_page'))


@course_bp.route('/api/courses/<int:course_id>', methods=['DELETE'])
@login_required
def api_delete_course(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    if not can_manage_course(course):
        return jsonify({'success': False, 'error': 'forbidden'}), 403
    delete_course(course_id, get_current_user())
    return jsonify({'success': True, 'deleted_course_id': course_id})