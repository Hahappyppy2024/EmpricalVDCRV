from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
from models.assignment import AssignmentRepository, SubmissionRepository
from models.upload import UploadRepository
from models.course import CourseRepository
from decorators import login_required, role_required

asg_bp = Blueprint('assignment', __name__)


# --- Assignments ---

@asg_bp.route('/courses/<int:course_id>/assignments', methods=['GET'])
@login_required
def list_assignments(course_id):
    assignments = AssignmentRepository.list_by_course(course_id)
    if request.path.startswith('/api'):
        return jsonify([dict(a) for a in assignments])
    return render_template('assignments/list.html', assignments=assignments, course_id=course_id)


@asg_bp.route('/courses/<int:course_id>/assignments/new', methods=['GET'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def new_assignment(course_id):
    return render_template('assignments/form.html', course_id=course_id, assignment=None)


@asg_bp.route('/courses/<int:course_id>/assignments', methods=['POST'])
@asg_bp.route('/api/courses/<int:course_id>/assignments', methods=['POST'])
# R01
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant','student'])
def create_assignment(course_id):
    data = request.get_json() if request.is_json else request.form
    asg_id = AssignmentRepository.create(
        course_id, session['user_id'], data.get('title'), data.get('description'), data.get('due_at')
    )
    if request.is_json:
        return jsonify({'assignment_id': asg_id}), 201
    return redirect(url_for('assignment.get_assignment', course_id=course_id, assignment_id=asg_id))


@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['GET'])
@login_required
def get_assignment(course_id, assignment_id):
    asg = AssignmentRepository.get(assignment_id)
    if not asg:
        return "Assignment not found", 404

    my_sub = None
    role = CourseRepository.get_member_role(course_id, session['user_id'])
    if role == 'student':
        my_sub = SubmissionRepository.get_by_student(assignment_id, session['user_id'])

    if request.path.startswith('/api'):
        return jsonify({'assignment': dict(asg), 'my_submission': dict(my_sub) if my_sub else None})
    # return render_template('assignments/detail.html', assignment=asg, my_submission=my_sub, course_id=course_id)
    return render_template(
        'assignments/detail.html',
        assignment=asg,
        my_submission=my_sub,
        course_id=course_id,
        role=role
    )

@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/edit', methods=['GET'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def edit_assignment(course_id, assignment_id):
    asg = AssignmentRepository.get(assignment_id)
    return render_template('assignments/form.html', course_id=course_id, assignment=asg)


# HTML Update
@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['POST'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def update_assignment_html(course_id, assignment_id):
    data = request.form
    AssignmentRepository.update(assignment_id, data.get('title'), data.get('description'), data.get('due_at'))
    return redirect(url_for('assignment.get_assignment', course_id=course_id, assignment_id=assignment_id))


# API Update
@asg_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['PUT'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def update_assignment_api(course_id, assignment_id):
    data = request.get_json()
    AssignmentRepository.update(assignment_id, data.get('title'), data.get('description'), data.get('due_at'))
    return jsonify({'message': 'Updated'})


# Delete
@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/delete', methods=['POST'])
@asg_bp.route('/api/courses/<int:course_id>/assignments/<int:assignment_id>', methods=['DELETE'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def delete_assignment(course_id, assignment_id):
    AssignmentRepository.delete(assignment_id)
    if request.is_json:
        return jsonify({'message': 'Deleted'})
    return redirect(url_for('assignment.list_assignments', course_id=course_id))


# --- Submissions ---

@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submit', methods=['GET'])
@role_required(course_id_kw='course_id', roles=['student'])
def create_submission_form(course_id, assignment_id):
    return render_template('assignments/submit_form.html', course_id=course_id, assignment_id=assignment_id)


@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['POST'])
@role_required(course_id_kw='course_id', roles=['student'])
def create_submission(course_id, assignment_id):
    # 处理文件上传和表单数据
    upload_id = None
    if 'file' in request.files:
        file = request.files['file']
        if file.filename:
            upload_id = UploadRepository.save_file(file, course_id, session['user_id'])

    content_text = request.form.get('content_text')

    # 如果是 API JSON 请求
    if request.is_json:
        data = request.get_json()
        content_text = data.get('content_text')
        upload_id = data.get('attachment_upload_id')  # API 传递 ID

    existing = SubmissionRepository.get_by_student(assignment_id, session['user_id'])
    if existing:
        SubmissionRepository.update(existing['submission_id'], content_text, upload_id)
        sub_id = existing['submission_id']
    else:
        sub_id = SubmissionRepository.create(assignment_id, course_id, session['user_id'], content_text, upload_id)

    if request.is_json:
        return jsonify({'submission_id': sub_id}), 201
    return redirect(url_for('assignment.get_assignment', course_id=course_id, assignment_id=assignment_id))


@asg_bp.route('/courses/<int:course_id>/assignments/<int:assignment_id>/submissions', methods=['GET'])
@role_required(course_id_kw='course_id', roles=['admin', 'teacher', 'assistant', 'senior-assistant'])
def list_submissions(course_id, assignment_id):
    subs = SubmissionRepository.list_for_assignment(assignment_id)
    if request.path.startswith('/api'):
        return jsonify([dict(s) for s in subs])
    return render_template('assignments/submissions.html', submissions=subs, course_id=course_id,
                           assignment_id=assignment_id)


@asg_bp.route('/api/my/submissions', methods=['GET'])
@login_required
def api_my_submissions():
    subs = SubmissionRepository.list_my_submissions(session['user_id'])
    return jsonify([dict(s) for s in subs])


# API List Assignments
@asg_bp.route('/api/courses/<int:course_id>/assignments', methods=['GET'])
def api_list_assignments(course_id):
    return list_assignments(course_id)


@asg_bp.route('/courses/<int:course_id>/assignments/new', methods=['GET'])
@role_required(course_id_kw='course_id', roles=['admin','teacher','assistant','senior-assistant'])
def new_assignment_form(course_id):
    return render_template('assignments/new.html', course_id=course_id)