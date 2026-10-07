import os
import uuid
from pathlib import Path

from flask import Blueprint, current_app, jsonify, redirect, render_template, request, send_file, url_for
from werkzeug.utils import secure_filename

from models.assignment_repo import create_assignment, delete_assignment, get_assignment_by_id, list_assignments, update_assignment
from models.course_repo import get_course_by_id
from models.submission_repo import create_submission, get_submission_by_id, list_submissions_for_assignment, list_submissions_for_student, update_submission
from models.upload_repo import create_upload, delete_upload, get_upload_by_id, list_uploads
from utils.auth import (
    get_course_membership,
    get_current_user,
    get_or_create_csrf_token,
    login_required,
    require_course_member,
    require_course_staff,
    validate_csrf_token,
)


assignment_bp = Blueprint('assignments', __name__)


def _assignment_to_dict(item):
    if item is None:
        return None
    return {
        'assignment_id': item['assignment_id'],
        'course_id': item['course_id'],
        'created_by': item['created_by'],
        'title': item['title'],
        'description': item['description'],
        'due_at': item['due_at'],
        'created_at': item['created_at'],
        'updated_at': item['updated_at'],
        'username': item['username'],
        'display_name': item['display_name'],
    }


def _submission_to_dict(item):
    if item is None:
        return None
    return {
        'submission_id': item['submission_id'],
        'assignment_id': item['assignment_id'],
        'course_id': item['course_id'],
        'student_id': item['student_id'],
        'content_text': item['content_text'],
        'attachment_upload_id': item['attachment_upload_id'],
        'attachment_name': item['attachment_name'],
        'created_at': item['created_at'],
        'updated_at': item['updated_at'],
        'username': item['username'],
        'display_name': item['display_name'],
    }


def _upload_to_dict(item):
    if item is None:
        return None
    return {
        'upload_id': item['upload_id'],
        'course_id': item['course_id'],
        'uploaded_by': item['uploaded_by'],
        'original_name': item['original_name'],
        'is_student_visible': bool(item['is_student_visible']),
        'allow_submission_attachment': bool(item['allow_submission_attachment']),
        'created_at': item['created_at'],
        'username': item['username'],
        'display_name': item['display_name'],
    }


def _load_course_assignment(course_id, assignment_id):
    course = get_course_by_id(course_id)
    assignment = get_assignment_by_id(course_id, assignment_id) if course is not None else None
    return course, assignment


def _is_staff_role(role_in_course):
    return role_in_course in {'teacher', 'admin', 'assistant', 'senior-assistant'}


def _current_user_is_course_staff(course_id):
    membership = get_course_membership(course_id)
    return membership is not None and _is_staff_role(membership['role_in_course'])


def _upload_is_student_visible(upload):
    if upload is None:
        return False
    return bool(upload['is_student_visible'])


def _can_view_upload(upload):
    current_user = get_current_user()
    if upload is None or current_user is None:
        return False
    if _current_user_is_course_staff(upload['course_id']):
        return True
    if upload['uploaded_by'] == current_user['user_id']:
        return True
    return _upload_is_student_visible(upload)


def _allowed_uploads_for_user(course_id):
    return [item for item in list_uploads(course_id) if _can_view_upload(item)]


def _can_attach_upload(upload):
    current_user = get_current_user()
    if upload is None or current_user is None:
        return False
    if _current_user_is_course_staff(upload['course_id']):
        return True
    if upload['uploaded_by'] == current_user['user_id']:
        return True
    return bool(upload['allow_submission_attachment'])


def _attachment_upload_id_from_value_for_user(course_id, value):
    if value in (None, '', 'null'):
        return None
    try:
        upload_id = int(value)
    except (TypeError, ValueError):
        return 'invalid'
    upload = get_upload_by_id(course_id, upload_id)
    if upload is None or not _can_attach_upload(upload):
        return 'invalid'
    return upload_id


def _validate_form_csrf():
    return validate_csrf_token(request.form.get('csrf_token', ''))


def _attachment_upload_id_from_value(course_id, value):
    if value in (None, '', 'null'):
        return None
    try:
        upload_id = int(value)
    except (TypeError, ValueError):
        return 'invalid'
    upload = get_upload_by_id(course_id, upload_id)
    if upload is None:
        return 'invalid'
    return upload_id


def _can_access_submission(submission):
    current_user = get_current_user()
    if submission is None or current_user is None:
        return False
    if submission['student_id'] == current_user['user_id']:
        return True
    return False


@assignment_bp.route('/courses/<int:course_id>/assignments/new', methods=['GET'])
@login_required
@require_course_staff
def new_assignment_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('assignments/new.html', course=course, current_user=get_current_user(), csrf_token=get_or_create_csrf_token())


@assignment_bp.route('/courses/<int:course_id>/assignments', methods=['POST'])
@login_required
@require_course_staff
def create_assignment_submit(course_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    due_at = request.form.get('due_at', '').strip()
    if not title:
        return render_template('assignments/new.html', course=course, error='Title is required.', current_user=get_current_user(), csrf_token=get_or_create_csrf_token()), 400
    item = create_assignment(course_id, get_current_user()['user_id'], title, description, due_at)
    return redirect(url_for('assignments.get_assignment_page', course_id=course_id, assignment_id=item['assignment_id']))


@assignment_bp.route('/api/courses/<int:course_id>/assignments', methods=['POST'])
@login_required
@require_course_staff
def api_create_assignment(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    due_at = str(data.get('due_at', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    item = create_assignment(course_id, get_current_user()['user_id'], title, description, due_at)
    return jsonify({'success': True, 'assignment': _assignment_to_dict(item)}), 201


@assignment_bp.route('/courses/<int:course_id>/assignments', methods=['GET'])
@login_required
@require_course_member
def list_assignments_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('assignments/list.html', course=course, assignments=list_assignments(course_id), current_user=get_current_user())


@assignment_bp.route('/api/courses/<int:course_id>/assignments', methods=['GET'])
@login_required
@require_course_member
def api_list_assignments(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'assignments': [_assignment_to_dict(item) for item in list_assignments(course_id)]})


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['GET'])
@login_required
@require_course_member
def get_assignment_page(course_id, assignment_id):
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('assignments/detail.html', course=course, assignment=assignment, current_user=get_current_user(), csrf_token=get_or_create_csrf_token())


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['GET'])
@login_required
@require_course_member
def api_get_assignment(course_id, assignment_id):
    item = get_assignment_by_id(course_id, assignment_id)
    if item is None:
        return jsonify({'success': False, 'error': 'assignment_not_found'}), 404
    return jsonify({'success': True, 'assignment': _assignment_to_dict(item)})


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/edit', methods=['GET'])
@login_required
@require_course_staff
def edit_assignment_page(course_id, assignment_id):
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('assignments/edit.html', course=course, assignment=assignment, current_user=get_current_user(), csrf_token=get_or_create_csrf_token())


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['POST'])
@login_required
@require_course_staff
def update_assignment_submit(course_id, assignment_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    due_at = request.form.get('due_at', '').strip()
    if not title:
        return render_template('assignments/edit.html', course=course, assignment=assignment, error='Title is required.', current_user=get_current_user(), csrf_token=get_or_create_csrf_token()), 400
    update_assignment(course_id, assignment_id, title, description, due_at)
    return redirect(url_for('assignments.get_assignment_page', course_id=course_id, assignment_id=assignment_id))


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['PUT'])
@login_required
@require_course_staff
def api_update_assignment(course_id, assignment_id):
    item = get_assignment_by_id(course_id, assignment_id)
    if item is None:
        return jsonify({'success': False, 'error': 'assignment_not_found'}), 404
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    due_at = str(data.get('due_at', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    item = update_assignment(course_id, assignment_id, title, description, due_at)
    return jsonify({'success': True, 'assignment': _assignment_to_dict(item)})


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/delete', methods=['POST'])
@login_required
@require_course_staff
def delete_assignment_submit(course_id, assignment_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    delete_assignment(course_id, assignment_id)
    return redirect(url_for('assignments.list_assignments_page', course_id=course_id))


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['DELETE'])
@login_required
@require_course_staff
def api_delete_assignment(course_id, assignment_id):
    item = get_assignment_by_id(course_id, assignment_id)
    if item is None:
        return jsonify({'success': False, 'error': 'assignment_not_found'}), 404
    delete_assignment(course_id, assignment_id)
    return jsonify({'success': True, 'deleted_assignment_id': assignment_id})


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submit', methods=['GET'])
@login_required
@require_course_member
def submit_assignment_page(course_id, assignment_id):
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'submissions/new.html',
        course=course,
        assignment=assignment,
        uploads=[item for item in _allowed_uploads_for_user(course_id) if _can_attach_upload(item)],
        current_user=get_current_user(),
        csrf_token=get_or_create_csrf_token(),
    )


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['POST'])
@login_required
@require_course_member
def create_submission_submit(course_id, assignment_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    content_text = request.form.get('content_text', '').strip()
    attachment_upload_id = _attachment_upload_id_from_value_for_user(course_id, request.form.get('attachment_upload_id'))
    if attachment_upload_id == 'invalid':
        return render_template(
            'submissions/new.html',
            course=course,
            assignment=assignment,
            uploads=[item for item in _allowed_uploads_for_user(course_id) if _can_attach_upload(item)],
            error='Attachment upload is invalid.',
            current_user=get_current_user(),
            csrf_token=get_or_create_csrf_token(),
        ), 400
    item = create_submission(assignment_id, course_id, get_current_user()['user_id'], content_text, attachment_upload_id)
    return redirect(url_for('assignments.my_submissions_page'))


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['POST'])
@login_required
@require_course_member
def api_create_submission(course_id, assignment_id):
    if get_assignment_by_id(course_id, assignment_id) is None:
        return jsonify({'success': False, 'error': 'assignment_not_found'}), 404
    data = request.get_json(silent=True) or {}
    content_text = str(data.get('content_text', '')).strip()
    attachment_upload_id = _attachment_upload_id_from_value_for_user(course_id, data.get('attachment_upload_id'))
    if attachment_upload_id == 'invalid':
        return jsonify({'success': False, 'error': 'attachment_upload_invalid'}), 400
    item = create_submission(assignment_id, course_id, get_current_user()['user_id'], content_text, attachment_upload_id)
    return jsonify({'success': True, 'submission': _submission_to_dict(item)}), 201


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>', methods=['POST'])
@login_required
@require_course_member
def update_submission_submit(course_id, assignment_id, submission_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    submission = get_submission_by_id(course_id, assignment_id, submission_id)
    if submission is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not _can_access_submission(submission):
        return redirect(url_for('assignments.my_submissions_page'))
    content_text = request.form.get('content_text', '').strip()
    attachment_upload_id = _attachment_upload_id_from_value_for_user(course_id, request.form.get('attachment_upload_id'))
    if attachment_upload_id == 'invalid':
        course, assignment = _load_course_assignment(course_id, assignment_id)
        return render_template(
            'submissions/edit.html',
            course=course,
            assignment=assignment,
            submission=submission,
            uploads=[item for item in _allowed_uploads_for_user(course_id) if _can_attach_upload(item)],
            error='Attachment upload is invalid.',
            current_user=get_current_user(),
            csrf_token=get_or_create_csrf_token(),
        ), 400
    update_submission(course_id, assignment_id, submission_id, content_text, attachment_upload_id)
    return redirect(url_for('assignments.my_submissions_page'))


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>', methods=['PUT'])
@login_required
@require_course_member
def api_update_submission(course_id, assignment_id, submission_id):
    submission = get_submission_by_id(course_id, assignment_id, submission_id)
    if submission is None:
        return jsonify({'success': False, 'error': 'submission_not_found'}), 404
    if not _can_access_submission(submission):
        return jsonify({'success': False, 'error': 'submission_owner_required'}), 403
    data = request.get_json(silent=True) or {}
    content_text = str(data.get('content_text', '')).strip()
    attachment_upload_id = _attachment_upload_id_from_value_for_user(course_id, data.get('attachment_upload_id'))
    if attachment_upload_id == 'invalid':
        return jsonify({'success': False, 'error': 'attachment_upload_invalid'}), 400
    item = update_submission(course_id, assignment_id, submission_id, content_text, attachment_upload_id)
    return jsonify({'success': True, 'submission': _submission_to_dict(item)})


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>/edit', methods=['GET'])
@login_required
@require_course_member
def edit_submission_page(course_id, assignment_id, submission_id):
    course, assignment = _load_course_assignment(course_id, assignment_id)
    submission = get_submission_by_id(course_id, assignment_id, submission_id)
    if course is None or assignment is None or submission is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not _can_access_submission(submission):
        return redirect(url_for('assignments.my_submissions_page'))
    return render_template(
        'submissions/edit.html',
        course=course,
        assignment=assignment,
        submission=submission,
        uploads=[item for item in _allowed_uploads_for_user(course_id) if _can_attach_upload(item)],
        current_user=get_current_user(),
        csrf_token=get_or_create_csrf_token(),
    )


@assignment_bp.route('/courses/<int:course_id>/submissions/mine', methods=['GET'])
@login_required
def my_submissions_page():
    user = get_current_user()
    return render_template('submissions/my.html', submissions=list_submissions_for_student(user['user_id']), current_user=user)


@assignment_bp.route('/api/submissions/mine', methods=['GET'])
@login_required
def api_my_submissions():
    user = get_current_user()
    return jsonify({'success': True, 'submissions': [_submission_to_dict(item) for item in list_submissions_for_student(user['user_id'])]})


@assignment_bp.route('/api/my/submissions', methods=['GET'])
@login_required
def api_my_submissions_alias():
    return api_my_submissions()


@assignment_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['GET'])
@login_required
@require_course_staff
def list_submissions_for_assignment_page(course_id, assignment_id):
    course, assignment = _load_course_assignment(course_id, assignment_id)
    if course is None or assignment is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('submissions/list.html', course=course, assignment=assignment, submissions=list_submissions_for_assignment(course_id, assignment_id), current_user=get_current_user())


@assignment_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['GET'])
@login_required
@require_course_staff
def api_list_submissions_for_assignment(course_id, assignment_id):
    if get_assignment_by_id(course_id, assignment_id) is None:
        return jsonify({'success': False, 'error': 'assignment_not_found'}), 404
    return jsonify({'success': True, 'submissions': [_submission_to_dict(item) for item in list_submissions_for_assignment(course_id, assignment_id)]})


@assignment_bp.route('/courses/<int:course_id>/uploads/new', methods=['GET'])
@login_required
@require_course_staff
def new_upload_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('uploads/new.html', course=course, current_user=get_current_user(), csrf_token=get_or_create_csrf_token())


@assignment_bp.route('/courses/<int:course_id>/uploads', methods=['POST'])
@login_required
@require_course_staff
def create_upload_submit(course_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    uploaded_file = request.files.get('file')
    if uploaded_file is None or not uploaded_file.filename:
        return render_template('uploads/new.html', course=course, error='File is required.', current_user=get_current_user(), csrf_token=get_or_create_csrf_token()), 400
    student_visible = _coerce_bool_flag(request.form.get('student_visible'))
    allow_submission_attachment = _coerce_bool_flag(request.form.get('allow_submission_attachment'))
    _save_upload(course_id, uploaded_file, student_visible, allow_submission_attachment)
    return redirect(url_for('assignments.list_uploads_page', course_id=course_id))


@assignment_bp.route('/api/courses/<int:course_id>/uploads', methods=['POST'])
@login_required
@require_course_staff
def api_create_upload(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    uploaded_file = request.files.get('file')
    if uploaded_file is None or not uploaded_file.filename:
        return jsonify({'success': False, 'error': 'file_required'}), 400
    student_visible = _coerce_bool_flag(request.form.get('student_visible'))
    allow_submission_attachment = _coerce_bool_flag(request.form.get('allow_submission_attachment'))
    item = _save_upload(course_id, uploaded_file, student_visible, allow_submission_attachment)
    return jsonify({'success': True, 'upload': _upload_to_dict(item)}), 201


def _default_student_visible(original_name):
    name = (original_name or '').lower()
    return 'handout' in name


def _coerce_bool_flag(value):
    if isinstance(value, bool):
        return value
    if value is None:
        return None
    value = str(value).strip().lower()
    if value in {'1', 'true', 'yes', 'on'}:
        return True
    if value in {'0', 'false', 'no', 'off'}:
        return False
    return None


def _save_upload(course_id, uploaded_file, student_visible=None, allow_submission_attachment=None):
    original_name = secure_filename(uploaded_file.filename)
    if not original_name:
        original_name = 'upload.bin'
    if student_visible is None:
        student_visible = _default_student_visible(original_name)
    if allow_submission_attachment is None:
        allow_submission_attachment = bool(student_visible)
    stored_name = f'{uuid.uuid4().hex}_{original_name}'
    upload_root = current_app.config.get('UPLOAD_ROOT')
    if not upload_root:
        upload_root = os.path.join(current_app.root_path, 'storage', 'uploads')
    Path(upload_root).mkdir(parents=True, exist_ok=True)
    full_path = Path(upload_root) / stored_name
    uploaded_file.save(full_path)
    return create_upload(course_id, get_current_user()['user_id'], original_name, str(full_path), student_visible, allow_submission_attachment)


@assignment_bp.route('/courses/<int:course_id>/uploads', methods=['GET'])
@login_required
@require_course_member
def list_uploads_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'uploads/list.html',
        course=course,
        uploads=_allowed_uploads_for_user(course_id),
        current_user=get_current_user(),
        can_manage_uploads=_current_user_is_course_staff(course_id),
        csrf_token=get_or_create_csrf_token(),
    )


@assignment_bp.route('/api/courses/<int:course_id>/uploads', methods=['GET'])
@login_required
@require_course_member
def api_list_uploads(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'uploads': [_upload_to_dict(item) for item in _allowed_uploads_for_user(course_id)]})


def _download_upload_response(course_id, upload_id):
    item = get_upload_by_id(course_id, upload_id)
    if item is None or not _can_view_upload(item) or not os.path.exists(item['storage_path']):
        return None
    return send_file(item['storage_path'], as_attachment=True, download_name=item['original_name'])


@assignment_bp.route('/courses/<int:course_id>/uploads/<int:upload_id>/download', methods=['GET'])
@login_required
@require_course_member
def download_upload(course_id, upload_id):
    response = _download_upload_response(course_id, upload_id)
    if response is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return response


@assignment_bp.route('/api/courses/<int:course_id>/uploads/<int:upload_id>/download', methods=['GET'])
@login_required
@require_course_member
def api_download_upload(course_id, upload_id):
    response = _download_upload_response(course_id, upload_id)
    if response is None:
        return jsonify({'success': False, 'error': 'upload_not_found'}), 404
    return response


@assignment_bp.route('/courses/<int:course_id>/uploads/<int:upload_id>/delete', methods=['POST'])
@login_required
@require_course_staff
def delete_upload_submit(course_id, upload_id):
    if not _validate_form_csrf():
        return 'Forbidden', 403
    item = get_upload_by_id(course_id, upload_id)
    if item is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if os.path.exists(item['storage_path']):
        os.remove(item['storage_path'])
    delete_upload(course_id, upload_id)
    return redirect(url_for('assignments.list_uploads_page', course_id=course_id))


@assignment_bp.route('/api/courses/<int:course_id>/uploads/<int:upload_id>', methods=['DELETE'])
@login_required
@require_course_staff
def api_delete_upload(course_id, upload_id):
    item = get_upload_by_id(course_id, upload_id)
    if item is None:
        return jsonify({'success': False, 'error': 'upload_not_found'}), 404
    if os.path.exists(item['storage_path']):
        os.remove(item['storage_path'])
    delete_upload(course_id, upload_id)
    return jsonify({'success': True, 'deleted_upload_id': upload_id})