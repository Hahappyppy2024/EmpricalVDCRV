import json
import secrets
from datetime import datetime

from flask import Blueprint, jsonify, redirect, render_template, request, session, url_for

from models.course_repo import get_course_by_id
from models.question_repo import create_question, delete_question, get_question_by_id, list_questions, update_question
from models.quiz_repo import (
    add_quiz_question,
    create_quiz,
    create_quiz_attempt,
    delete_quiz,
    get_attempt_by_id,
    get_quiz_by_id,
    list_answers_for_attempt,
    list_attempts_for_student,
    list_quiz_questions,
    list_quizzes,
    quiz_has_question,
    remove_quiz_question,
    submit_quiz_attempt,
    update_quiz,
    upsert_quiz_answer,
)
from utils.auth import get_current_user, login_required, require_course_member, require_course_staff


quiz_bp = Blueprint('quizzes', __name__)


def _parse_quiz_datetime(value):
    value = (value or '').strip()
    if not value:
        return None
    for fmt in ('%Y-%m-%d %H:%M', '%Y-%m-%dT%H:%M', '%Y-%m-%d %H:%M:%S', '%Y-%m-%dT%H:%M:%S'):
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            continue
    return None


def _quiz_availability_error(quiz):
    now = datetime.now()
    open_at = _parse_quiz_datetime(quiz['open_at'])
    due_at = _parse_quiz_datetime(quiz['due_at'])
    if open_at is not None and now < open_at:
        return 'quiz_not_open'
    if due_at is not None and now > due_at:
        return 'quiz_closed'
    return None


def _get_or_create_csrf_token():
    token = session.get('_quiz_csrf_token')
    if not token:
        token = secrets.token_hex(32)
        session['_quiz_csrf_token'] = token
    return token


def _validate_csrf_token():
    expected = session.get('_quiz_csrf_token')
    provided = request.form.get('csrf_token', '')
    return bool(expected) and secrets.compare_digest(expected, provided)


def _csrf_error_response(course_id, fallback_endpoint='courses.get_course_page'):
    if fallback_endpoint == 'quizzes.my_quizzes_page':
        return redirect(url_for('quizzes.my_quizzes_page'))
    return redirect(url_for(fallback_endpoint, course_id=course_id))


def _require_form_csrf(course_id, fallback_endpoint='courses.get_course_page'):
    if _validate_csrf_token():
        return None
    return _csrf_error_response(course_id, fallback_endpoint)


def _quiz_window_html_response(course_id, quiz_id):
    return redirect(url_for('quizzes.get_quiz_page', course_id=course_id, quiz_id=quiz_id))


def _attempt_html_response():
    return redirect(url_for('quizzes.my_quizzes_page'))


@quiz_bp.app_context_processor
def inject_quiz_csrf_token():
    return {'csrf_token': _get_or_create_csrf_token}


def _question_to_dict(item):
    if item is None:
        return None
    return {
        'question_id': item['question_id'], 'course_id': item['course_id'], 'created_by': item['created_by'],
        'qtype': item['qtype'], 'prompt': item['prompt'], 'options_json': item['options_json'], 'answer_json': item['answer_json'],
        'created_at': item['created_at'], 'updated_at': item['updated_at'], 'username': item['username'], 'display_name': item['display_name'],
    }


def _quiz_to_dict(item):
    if item is None:
        return None
    return {
        'quiz_id': item['quiz_id'], 'course_id': item['course_id'], 'created_by': item['created_by'], 'title': item['title'],
        'description': item['description'], 'open_at': item['open_at'], 'due_at': item['due_at'],
        'created_at': item['created_at'], 'updated_at': item['updated_at'], 'username': item['username'], 'display_name': item['display_name'],
    }


def _quiz_question_to_dict(item):
    return {'quiz_id': item['quiz_id'], 'question_id': item['question_id'], 'points': item['points'], 'position': item['position'], 'prompt': item['prompt'], 'qtype': item['qtype']}


def _attempt_to_dict(item, answers=None):
    if item is None:
        return None
    data = {
        'attempt_id': item['attempt_id'], 'quiz_id': item['quiz_id'], 'course_id': item['course_id'], 'student_id': item['student_id'],
        'started_at': item['started_at'], 'submitted_at': item['submitted_at'],
    }
    if 'username' in item.keys():
        data['username'] = item['username']
        data['display_name'] = item['display_name']
    if 'quiz_title' in item.keys():
        data['quiz_title'] = item['quiz_title']
    if 'course_title' in item.keys():
        data['course_title'] = item['course_title']
    if answers is not None:
        data['answers'] = answers
    return data


def _answer_to_dict(item):
    return {'answer_id': item['answer_id'], 'attempt_id': item['attempt_id'], 'question_id': item['question_id'], 'answer_json': item['answer_json'], 'created_at': item['created_at'], 'prompt': item['prompt'], 'qtype': item['qtype']}


def _json_text(value):
    if value in (None, ''):
        return ''
    if isinstance(value, str):
        return value
    return json.dumps(value)


def _current_role(course_id):
    from utils.auth import get_course_membership
    membership = get_course_membership(course_id)
    return membership['role_in_course'] if membership else None


def _student_only(course_id):
    role = _current_role(course_id)
    return role == 'student'


@quiz_bp.route('/courses/<int:course_id>/questions/new', methods=['GET'])
@login_required
@require_course_staff
def new_question_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/question_new.html', course=course, current_user=get_current_user())


@quiz_bp.route('/courses/<int:course_id>/questions', methods=['POST'])
@login_required
@require_course_staff
def create_question_submit(course_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.list_questions_page')
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    qtype = request.form.get('qtype', '').strip()
    prompt = request.form.get('prompt', '').strip()
    options_json = request.form.get('options_json', '').strip()
    answer_json = request.form.get('answer_json', '').strip()
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not qtype or not prompt:
        return render_template('quizzes/question_new.html', course=course, error='qtype and prompt are required.', current_user=get_current_user()), 400
    item = create_question(course_id, get_current_user()['user_id'], qtype, prompt, options_json, answer_json)
    return redirect(url_for('quizzes.get_question_page', course_id=course_id, question_id=item['question_id']))


@quiz_bp.route('/api/courses/<int:course_id>/questions', methods=['POST'])
@login_required
@require_course_staff
def api_create_question(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    data = request.get_json(silent=True) or {}
    qtype = str(data.get('qtype', '')).strip()
    prompt = str(data.get('prompt', '')).strip()
    options_json = _json_text(data.get('options_json', ''))
    answer_json = _json_text(data.get('answer_json', ''))
    if not qtype or not prompt:
        return jsonify({'success': False, 'error': 'qtype_and_prompt_required'}), 400
    item = create_question(course_id, get_current_user()['user_id'], qtype, prompt, options_json, answer_json)
    return jsonify({'success': True, 'question': _question_to_dict(item)}), 201


@quiz_bp.route('/courses/<int:course_id>/questions', methods=['GET'])
@login_required
@require_course_staff
def list_questions_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/question_list.html', course=course, questions=list_questions(course_id), current_user=get_current_user())


@quiz_bp.route('/api/courses/<int:course_id>/questions', methods=['GET'])
@login_required
@require_course_staff
def api_list_questions(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'questions': [_question_to_dict(item) for item in list_questions(course_id)]})


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>', methods=['GET'])
@login_required
@require_course_staff
def get_question_page(course_id, question_id):
    course = get_course_by_id(course_id)
    item = get_question_by_id(course_id, question_id)
    if course is None or item is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/question_detail.html', course=course, question=item, current_user=get_current_user())


@quiz_bp.route('/api/courses/<int:course_id>/questions/<int:question_id>', methods=['GET'])
@login_required
@require_course_staff
def api_get_question(course_id, question_id):
    item = get_question_by_id(course_id, question_id)
    if item is None:
        return jsonify({'success': False, 'error': 'question_not_found'}), 404
    return jsonify({'success': True, 'question': _question_to_dict(item)})


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>/edit', methods=['GET'])
@login_required
@require_course_staff
def edit_question_page(course_id, question_id):
    course = get_course_by_id(course_id)
    item = get_question_by_id(course_id, question_id)
    if course is None or item is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/question_edit.html', course=course, question=item, current_user=get_current_user())


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>', methods=['POST'])
@login_required
@require_course_staff
def update_question_submit(course_id, question_id):
    csrf_error = _require_form_csrf(course_id)
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    item = get_question_by_id(course_id, question_id)
    if course is None or item is None:
        return render_template('404.html', current_user=get_current_user()), 404
    qtype = request.form.get('qtype', '').strip()
    prompt = request.form.get('prompt', '').strip()
    options_json = request.form.get('options_json', '').strip()
    answer_json = request.form.get('answer_json', '').strip()
    if not qtype or not prompt:
        return render_template('quizzes/question_edit.html', course=course, question=item, error='qtype and prompt are required.', current_user=get_current_user()), 400
    update_question(course_id, question_id, qtype, prompt, options_json, answer_json)
    return redirect(url_for('quizzes.get_question_page', course_id=course_id, question_id=question_id))


@quiz_bp.route('/api/courses/<int:course_id>/questions/<int:question_id>', methods=['PUT'])
@login_required
@require_course_staff
def api_update_question(course_id, question_id):
    if get_question_by_id(course_id, question_id) is None:
        return jsonify({'success': False, 'error': 'question_not_found'}), 404
    data = request.get_json(silent=True) or {}
    qtype = str(data.get('qtype', '')).strip()
    prompt = str(data.get('prompt', '')).strip()
    options_json = _json_text(data.get('options_json', ''))
    answer_json = _json_text(data.get('answer_json', ''))
    if not qtype or not prompt:
        return jsonify({'success': False, 'error': 'qtype_and_prompt_required'}), 400
    item = update_question(course_id, question_id, qtype, prompt, options_json, answer_json)
    return jsonify({'success': True, 'question': _question_to_dict(item)})


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>/delete', methods=['POST'])
@login_required
@require_course_staff
def delete_question_submit(course_id, question_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.list_questions_page')
    if csrf_error is not None:
        return csrf_error
    if get_question_by_id(course_id, question_id) is None:
        return render_template('404.html', current_user=get_current_user()), 404
    delete_question(course_id, question_id)
    return redirect(url_for('quizzes.list_questions_page', course_id=course_id))


@quiz_bp.route('/api/courses/<int:course_id>/questions/<int:question_id>', methods=['DELETE'])
@login_required
@require_course_staff
def api_delete_question(course_id, question_id):
    if get_question_by_id(course_id, question_id) is None:
        return jsonify({'success': False, 'error': 'question_not_found'}), 404
    delete_question(course_id, question_id)
    return jsonify({'success': True, 'deleted_question_id': question_id})


@quiz_bp.route('/courses/<int:course_id>/quizzes/new', methods=['GET'])
@login_required
@require_course_staff
def new_quiz_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/quiz_new.html', course=course, current_user=get_current_user())


@quiz_bp.route('/courses/<int:course_id>/quizzes', methods=['POST'])
@login_required
@require_course_staff
def create_quiz_submit(course_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.list_quizzes_page')
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    open_at = request.form.get('open_at', '').strip()
    due_at = request.form.get('due_at', '').strip()
    if not title:
        return render_template('quizzes/quiz_new.html', course=course, error='Title is required.', current_user=get_current_user()), 400
    item = create_quiz(course_id, get_current_user()['user_id'], title, description, open_at, due_at)
    return redirect(url_for('quizzes.get_quiz_page', course_id=course_id, quiz_id=item['quiz_id']))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes', methods=['POST'])
@login_required
@require_course_staff
def api_create_quiz(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    open_at = str(data.get('open_at', '')).strip()
    due_at = str(data.get('due_at', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    item = create_quiz(course_id, get_current_user()['user_id'], title, description, open_at, due_at)
    return jsonify({'success': True, 'quiz': _quiz_to_dict(item)}), 201


@quiz_bp.route('/courses/<int:course_id>/quizzes', methods=['GET'])
@login_required
@require_course_member
def list_quizzes_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/quiz_list.html', course=course, quizzes=list_quizzes(course_id), current_user=get_current_user())


@quiz_bp.route('/api/courses/<int:course_id>/quizzes', methods=['GET'])
@login_required
@require_course_member
def api_list_quizzes(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    return jsonify({'success': True, 'quizzes': [_quiz_to_dict(item) for item in list_quizzes(course_id)]})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['GET'])
@login_required
@require_course_member
def get_quiz_page(course_id, quiz_id):
    course = get_course_by_id(course_id)
    quiz = get_quiz_by_id(course_id, quiz_id)
    if course is None or quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/quiz_detail.html', course=course, quiz=quiz, quiz_questions=list_quiz_questions(course_id, quiz_id), current_user=get_current_user())


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['GET'])
@login_required
@require_course_member
def api_get_quiz(course_id, quiz_id):
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    return jsonify({'success': True, 'quiz': _quiz_to_dict(quiz), 'quiz_questions': [_quiz_question_to_dict(item) for item in list_quiz_questions(course_id, quiz_id)]})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/edit', methods=['GET'])
@login_required
@require_course_staff
def edit_quiz_page(course_id, quiz_id):
    course = get_course_by_id(course_id)
    quiz = get_quiz_by_id(course_id, quiz_id)
    if course is None or quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/quiz_edit.html', course=course, quiz=quiz, current_user=get_current_user())


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['POST'])
@login_required
@require_course_staff
def update_quiz_submit(course_id, quiz_id):
    csrf_error = _require_form_csrf(course_id)
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    quiz = get_quiz_by_id(course_id, quiz_id)
    if course is None or quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    title = request.form.get('title', '').strip()
    description = request.form.get('description', '').strip()
    open_at = request.form.get('open_at', '').strip()
    due_at = request.form.get('due_at', '').strip()
    if not title:
        return render_template('quizzes/quiz_edit.html', course=course, quiz=quiz, error='Title is required.', current_user=get_current_user()), 400
    update_quiz(course_id, quiz_id, title, description, open_at, due_at)
    return redirect(url_for('quizzes.get_quiz_page', course_id=course_id, quiz_id=quiz_id))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['PUT'])
@login_required
@require_course_staff
def api_update_quiz(course_id, quiz_id):
    if get_quiz_by_id(course_id, quiz_id) is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    data = request.get_json(silent=True) or {}
    title = str(data.get('title', '')).strip()
    description = str(data.get('description', '')).strip()
    open_at = str(data.get('open_at', '')).strip()
    due_at = str(data.get('due_at', '')).strip()
    if not title:
        return jsonify({'success': False, 'error': 'title_required'}), 400
    quiz = update_quiz(course_id, quiz_id, title, description, open_at, due_at)
    return jsonify({'success': True, 'quiz': _quiz_to_dict(quiz)})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/delete', methods=['POST'])
@login_required
@require_course_staff
def delete_quiz_submit(course_id, quiz_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.list_quizzes_page')
    if csrf_error is not None:
        return csrf_error
    if get_quiz_by_id(course_id, quiz_id) is None:
        return render_template('404.html', current_user=get_current_user()), 404
    delete_quiz(course_id, quiz_id)
    return redirect(url_for('quizzes.list_quizzes_page', course_id=course_id))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['DELETE'])
@login_required
@require_course_staff
def api_delete_quiz(course_id, quiz_id):
    if get_quiz_by_id(course_id, quiz_id) is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    delete_quiz(course_id, quiz_id)
    return jsonify({'success': True, 'deleted_quiz_id': quiz_id})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/questions', methods=['GET'])
@login_required
@require_course_staff
def configure_quiz_questions_page(course_id, quiz_id):
    course = get_course_by_id(course_id)
    quiz = get_quiz_by_id(course_id, quiz_id)
    if course is None or quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template('quizzes/quiz_questions.html', course=course, quiz=quiz, questions=list_questions(course_id), quiz_questions=list_quiz_questions(course_id, quiz_id), current_user=get_current_user())


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/questions', methods=['POST'])
@login_required
@require_course_staff
def configure_quiz_questions_submit(course_id, quiz_id):
    csrf_error = _require_form_csrf(course_id)
    if csrf_error is not None:
        return csrf_error
    course = get_course_by_id(course_id)
    quiz = get_quiz_by_id(course_id, quiz_id)
    if course is None or quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    try:
        question_id = int(request.form.get('question_id', '').strip())
        points = int(request.form.get('points', '0').strip() or '0')
        position = int(request.form.get('position', '0').strip() or '0')
    except ValueError:
        return render_template('quizzes/quiz_questions.html', course=course, quiz=quiz, questions=list_questions(course_id), quiz_questions=list_quiz_questions(course_id, quiz_id), error='question_id, points, and position must be integers.', current_user=get_current_user()), 400
    if add_quiz_question(course_id, quiz_id, question_id, points, position) is None:
        return render_template('quizzes/quiz_questions.html', course=course, quiz=quiz, questions=list_questions(course_id), quiz_questions=list_quiz_questions(course_id, quiz_id), error='Question not found.', current_user=get_current_user()), 404
    return redirect(url_for('quizzes.configure_quiz_questions_page', course_id=course_id, quiz_id=quiz_id))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions', methods=['POST'])
@login_required
@require_course_staff
def api_configure_quiz_questions(course_id, quiz_id):
    if get_quiz_by_id(course_id, quiz_id) is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    data = request.get_json(silent=True) or {}
    try:
        question_id = int(data.get('question_id'))
        points = int(data.get('points', 0))
        position = int(data.get('position', 0))
    except (TypeError, ValueError):
        return jsonify({'success': False, 'error': 'question_id_points_position_invalid'}), 400
    item = add_quiz_question(course_id, quiz_id, question_id, points, position)
    if item is None:
        return jsonify({'success': False, 'error': 'question_not_found'}), 404
    return jsonify({'success': True, 'quiz_question': _quiz_question_to_dict(item)})


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions/<int:question_id>', methods=['DELETE'])
@login_required
@require_course_staff
def api_remove_quiz_question(course_id, quiz_id, question_id):
    if get_quiz_by_id(course_id, quiz_id) is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    remove_quiz_question(quiz_id, question_id)
    return jsonify({'success': True, 'removed_question_id': question_id})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/start', methods=['POST'])
@login_required
@require_course_member
def start_attempt_submit(course_id, quiz_id):
    csrf_error = _require_form_csrf(course_id)
    if csrf_error is not None:
        return csrf_error
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if not _student_only(course_id):
        return redirect(url_for('quizzes.get_quiz_page', course_id=course_id, quiz_id=quiz_id))
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None:
        return _quiz_window_html_response(course_id, quiz_id)
    attempt = create_quiz_attempt(quiz_id, course_id, get_current_user()['user_id'])
    return redirect(url_for('quizzes.my_quizzes_page'))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/start', methods=['POST'])
@login_required
@require_course_member
def api_start_attempt(course_id, quiz_id):
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    if not _student_only(course_id):
        return jsonify({'success': False, 'error': 'student_only'}), 403
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None:
        return jsonify({'success': False, 'error': availability_error}), 403
    attempt = create_quiz_attempt(quiz_id, course_id, get_current_user()['user_id'])
    return jsonify({'success': True, 'attempt': _attempt_to_dict(attempt)}), 201


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers', methods=['POST'])
@login_required
@require_course_member
def answer_question_submit(course_id, quiz_id, attempt_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.my_quizzes_page')
    if csrf_error is not None:
        return csrf_error
    attempt = get_attempt_by_id(course_id, quiz_id, attempt_id)
    if attempt is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if attempt['student_id'] != get_current_user()['user_id'] or not _student_only(course_id):
        return redirect(url_for('quizzes.my_quizzes_page'))
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None or attempt['submitted_at'] is not None:
        return _attempt_html_response()
    try:
        question_id = int(request.form.get('question_id', '').strip())
    except ValueError:
        return redirect(url_for('quizzes.my_quizzes_page'))
    if not quiz_has_question(quiz_id, question_id):
        return _attempt_html_response()
    answer_json = request.form.get('answer_json', '').strip()
    upsert_quiz_answer(attempt_id, question_id, answer_json)
    return redirect(url_for('quizzes.my_quizzes_page'))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers', methods=['POST'])
@login_required
@require_course_member
def api_answer_question(course_id, quiz_id, attempt_id):
    attempt = get_attempt_by_id(course_id, quiz_id, attempt_id)
    if attempt is None:
        return jsonify({'success': False, 'error': 'attempt_not_found'}), 404
    if attempt['student_id'] != get_current_user()['user_id'] or not _student_only(course_id):
        return jsonify({'success': False, 'error': 'attempt_owner_student_required'}), 403
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None:
        return jsonify({'success': False, 'error': availability_error}), 403
    if attempt['submitted_at'] is not None:
        return jsonify({'success': False, 'error': 'attempt_already_submitted'}), 403
    data = request.get_json(silent=True) or {}
    try:
        question_id = int(data.get('question_id'))
    except (TypeError, ValueError):
        return jsonify({'success': False, 'error': 'question_id_invalid'}), 400
    if not quiz_has_question(quiz_id, question_id):
        return jsonify({'success': False, 'error': 'question_not_in_quiz'}), 400
    answer_json = _json_text(data.get('answer_json', ''))
    item = upsert_quiz_answer(attempt_id, question_id, answer_json)
    return jsonify({'success': True, 'answer': {'answer_id': item['answer_id'], 'attempt_id': item['attempt_id'], 'question_id': item['question_id'], 'answer_json': item['answer_json'], 'created_at': item['created_at']}})


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit', methods=['POST'])
@login_required
@require_course_member
def submit_attempt_submit(course_id, quiz_id, attempt_id):
    csrf_error = _require_form_csrf(course_id, 'quizzes.my_quizzes_page')
    if csrf_error is not None:
        return csrf_error
    attempt = get_attempt_by_id(course_id, quiz_id, attempt_id)
    if attempt is None:
        return render_template('404.html', current_user=get_current_user()), 404
    if attempt['student_id'] != get_current_user()['user_id'] or not _student_only(course_id):
        return redirect(url_for('quizzes.my_quizzes_page'))
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return render_template('404.html', current_user=get_current_user()), 404
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None or attempt['submitted_at'] is not None:
        return _attempt_html_response()
    submit_quiz_attempt(course_id, quiz_id, attempt_id)
    return redirect(url_for('quizzes.my_quizzes_page'))


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit', methods=['POST'])
@login_required
@require_course_member
def api_submit_attempt(course_id, quiz_id, attempt_id):
    attempt = get_attempt_by_id(course_id, quiz_id, attempt_id)
    if attempt is None:
        return jsonify({'success': False, 'error': 'attempt_not_found'}), 404
    if attempt['student_id'] != get_current_user()['user_id'] or not _student_only(course_id):
        return jsonify({'success': False, 'error': 'attempt_owner_student_required'}), 403
    quiz = get_quiz_by_id(course_id, quiz_id)
    if quiz is None:
        return jsonify({'success': False, 'error': 'quiz_not_found'}), 404
    availability_error = _quiz_availability_error(quiz)
    if availability_error is not None:
        return jsonify({'success': False, 'error': availability_error}), 403
    if attempt['submitted_at'] is not None:
        return jsonify({'success': False, 'error': 'attempt_already_submitted'}), 403
    item = submit_quiz_attempt(course_id, quiz_id, attempt_id)
    return jsonify({'success': True, 'attempt': _attempt_to_dict(item, [_answer_to_dict(a) for a in list_answers_for_attempt(attempt_id)])})


@quiz_bp.route('/my/quizzes', methods=['GET'])
@login_required
def my_quizzes_page():
    items = list_attempts_for_student(get_current_user()['user_id'])
    return render_template('quizzes/my_attempts.html', attempts=items, current_user=get_current_user())


@quiz_bp.route('/api/my/quizzes/attempts', methods=['GET'])
@login_required
def api_my_quizzes():
    items = list_attempts_for_student(get_current_user()['user_id'])
    return jsonify({'success': True, 'attempts': [_attempt_to_dict(item, [_answer_to_dict(a) for a in list_answers_for_attempt(item['attempt_id'])]) for item in items]})