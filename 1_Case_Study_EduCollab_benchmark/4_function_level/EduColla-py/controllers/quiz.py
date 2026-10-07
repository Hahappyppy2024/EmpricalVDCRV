from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
from models.quiz import QuestionRepository, QuizRepository, AttemptRepository
from decorators import login_required, role_required

quiz_bp = Blueprint('quiz', __name__)
STAFF = ['admin', 'teacher', 'assistant', 'senior-assistant']


# ============================================================
# Question Bank (HTML)
# ============================================================

@quiz_bp.route('/courses/<int:course_id>/questions', methods=['GET'])
@role_required(course_id_kw='course_id', roles=STAFF)
def list_questions(course_id):
    questions = QuestionRepository.list_by_course(course_id)
    return render_template('quizes/questions_list.html', questions=questions, course_id=course_id)


@quiz_bp.route('/courses/<int:course_id>/questions/new', methods=['GET'])
@role_required(course_id_kw='course_id', roles=STAFF)
def new_question_form(course_id):
    return render_template('quizes/question_form.html', course_id=course_id, question=None)


@quiz_bp.route('/courses/<int:course_id>/questions', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def create_question(course_id):
    data = request.form
    qid = QuestionRepository.create(
        course_id, session['user_id'],
        data.get('qtype'), data.get('prompt'),
        data.get('options_json'), data.get('answer_json')
    )
    return redirect(url_for('quiz.list_questions', course_id=course_id))


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>', methods=['GET'])
@role_required(course_id_kw='course_id', roles=STAFF)
def edit_question_form(course_id, question_id):
    q = QuestionRepository.get(question_id)
    if not q:
        return "Question not found", 404
    return render_template('quizes/question_form.html', question=q, course_id=course_id)


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def update_question_html(course_id, question_id):
    data = request.form
    QuestionRepository.update(
        question_id,
        data.get('qtype'),
        data.get('prompt'),
        data.get('options_json'),
        data.get('answer_json')
    )
    return redirect(url_for('quiz.list_questions', course_id=course_id))


@quiz_bp.route('/courses/<int:course_id>/questions/<int:question_id>/delete', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def delete_question_html(course_id, question_id):
    QuestionRepository.delete(question_id)
    return redirect(url_for('quiz.list_questions', course_id=course_id))


# ============================================================
# Quizzes (HTML)
# ============================================================

@quiz_bp.route('/courses/<int:course_id>/quizzes', methods=['GET'])
@login_required
def list_quizzes(course_id):
    quizzes = QuizRepository.list_by_course(course_id)
    return render_template('quizes/list.html', quizzes=quizzes, course_id=course_id)


@quiz_bp.route('/courses/<int:course_id>/quizzes/new', methods=['GET'])
@role_required(course_id_kw='course_id', roles=STAFF)
def new_quiz_form(course_id):
    return render_template('quizes/form.html', course_id=course_id, quiz=None)


@quiz_bp.route('/courses/<int:course_id>/quizzes', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def create_quiz(course_id):
    data = request.form
    qid = QuizRepository.create(
        course_id, session['user_id'],
        data.get('title'), data.get('description'),
        data.get('open_at'), data.get('due_at')
    )
    return redirect(url_for('quiz.get_quiz', course_id=course_id, quiz_id=qid))


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['GET'])
@login_required
def get_quiz(course_id, quiz_id):
    quiz = QuizRepository.get(quiz_id)
    if not quiz:
        return "Quiz not found", 404
    questions = QuizRepository.get_questions(quiz_id)
    return render_template('quizes/detail.html', quiz=quiz, questions=questions, course_id=course_id)


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/edit', methods=['GET'])
@role_required(course_id_kw='course_id', roles=STAFF)
def edit_quiz_form(course_id, quiz_id):
    quiz = QuizRepository.get(quiz_id)
    if not quiz:
        return "Quiz not found", 404
    return render_template('quizes/form.html', course_id=course_id, quiz=quiz)


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def update_quiz_html(course_id, quiz_id):
    data = request.form
    QuizRepository.update(
        quiz_id,
        data.get('title'),
        data.get('description'),
        data.get('open_at'),
        data.get('due_at')
    )
    return redirect(url_for('quiz.get_quiz', course_id=course_id, quiz_id=quiz_id))


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/delete', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def delete_quiz_html(course_id, quiz_id):
    QuizRepository.delete(quiz_id)
    return redirect(url_for('quiz.list_quizzes', course_id=course_id))


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/questions', methods=['GET', 'POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def configure_quiz_questions(course_id, quiz_id):
    if request.method == 'POST':
        data = request.form
        QuizRepository.add_question(
            quiz_id,
            data.get('question_id'),
            int(data.get('points', 1)),
            int(data.get('position', 0))
        )
        return redirect(url_for('quiz.get_quiz', course_id=course_id, quiz_id=quiz_id))

    questions = QuestionRepository.list_by_course(course_id)
    return render_template('quizes/configure.html', quiz_id=quiz_id, questions=questions, course_id=course_id)


# ============================================================
# Taking Quiz (HTML)
# ============================================================

@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/start', methods=['POST'])
@role_required(course_id_kw='course_id', roles=['student'])
def start_attempt(course_id, quiz_id):
    attempt_id = AttemptRepository.start(quiz_id, course_id, session['user_id'])
    return redirect(url_for('quiz.take_attempt', course_id=course_id, quiz_id=quiz_id, attempt_id=attempt_id))


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>', methods=['GET'])
@login_required
def take_attempt(course_id, quiz_id, attempt_id):
    attempt = AttemptRepository.get(attempt_id)
    if attempt['submitted_at']:
        return "Already submitted", 400

    questions = QuizRepository.get_questions(quiz_id)
    return render_template('quizes/take.html', attempt=attempt, questions=questions, course_id=course_id, quiz_id=quiz_id)


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers', methods=['POST'])
@login_required
def answer_question(course_id, quiz_id, attempt_id):
    data = request.form
    AttemptRepository.save_answer(attempt_id, data.get('question_id'), data.get('answer_json'))
    return redirect(url_for('quiz.take_attempt', course_id=course_id, quiz_id=quiz_id, attempt_id=attempt_id))


@quiz_bp.route('/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit', methods=['POST'])
@login_required
def submit_attempt(course_id, quiz_id, attempt_id):
    AttemptRepository.submit(attempt_id)
    return redirect(url_for('quiz.my_attempts'))


@quiz_bp.route('/my/quizzes', methods=['GET'])
@login_required
def my_attempts():
    attempts = AttemptRepository.get_my_attempts(session['user_id'])
    return render_template('quizes/my_attempts.html', attempts=attempts)


# ============================================================
# API (JSON) - minimal set (optional but cleaner)
# ============================================================

@quiz_bp.route('/api/courses/<int:course_id>/questions', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_create_question(course_id):
    data = request.get_json(silent=True) or {}
    qid = QuestionRepository.create(
        course_id, session['user_id'],
        data.get('qtype'), data.get('prompt'),
        data.get('options_json'), data.get('answer_json')
    )
    return jsonify({'question_id': qid}), 201


@quiz_bp.route('/api/courses/<int:course_id>/questions/<int:question_id>', methods=['PUT'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_update_question(course_id, question_id):
    data = request.get_json(silent=True) or {}
    QuestionRepository.update(
        question_id,
        data.get('qtype'),
        data.get('prompt'),
        data.get('options_json'),
        data.get('answer_json')
    )
    return jsonify({'message': 'Updated'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/questions/<int:question_id>', methods=['DELETE'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_delete_question(course_id, question_id):
    QuestionRepository.delete(question_id)
    return jsonify({'message': 'Deleted'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_create_quiz(course_id):
    data = request.get_json(silent=True) or {}
    qid = QuizRepository.create(
        course_id, session['user_id'],
        data.get('title'), data.get('description'),
        data.get('open_at'), data.get('due_at')
    )
    return jsonify({'quiz_id': qid}), 201


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['PUT'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_update_quiz(course_id, quiz_id):
    data = request.get_json(silent=True) or {}
    QuizRepository.update(
        quiz_id,
        data.get('title'), data.get('description'),
        data.get('open_at'), data.get('due_at')
    )
    return jsonify({'message': 'Updated'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>', methods=['DELETE'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_delete_quiz(course_id, quiz_id):
    QuizRepository.delete(quiz_id)
    return jsonify({'message': 'Deleted'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions', methods=['POST'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_add_quiz_question(course_id, quiz_id):
    data = request.get_json(silent=True) or {}
    QuizRepository.add_question(
        quiz_id,
        data.get('question_id'),
        int(data.get('points', 1)),
        int(data.get('position', 0))
    )
    return jsonify({'message': 'Question added'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions/<int:question_id>', methods=['DELETE'])
@role_required(course_id_kw='course_id', roles=STAFF)
def api_remove_quiz_question(course_id, quiz_id, question_id):
    QuizRepository.remove_question(quiz_id, question_id)
    return jsonify({'message': 'Removed'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/start', methods=['POST'])
@role_required(course_id_kw='course_id', roles=['student'])
def api_start_attempt(course_id, quiz_id):
    attempt_id = AttemptRepository.start(quiz_id, course_id, session['user_id'])
    return jsonify({'attempt_id': attempt_id}), 201


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers', methods=['POST'])
@login_required
def api_answer_question(course_id, quiz_id, attempt_id):
    data = request.get_json(silent=True) or {}
    AttemptRepository.save_answer(attempt_id, data.get('question_id'), data.get('answer_json'))
    return jsonify({'message': 'Saved'}), 200


@quiz_bp.route('/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit', methods=['POST'])
@login_required
def api_submit_attempt(course_id, quiz_id, attempt_id):
    AttemptRepository.submit(attempt_id)
    return jsonify({'message': 'Submitted'}), 200


@quiz_bp.route('/api/my/quizzes/attempts', methods=['GET'])
@login_required
def api_my_attempts():
    attempts = AttemptRepository.get_my_attempts(session['user_id'])
    return jsonify([dict(a) for a in attempts]), 200