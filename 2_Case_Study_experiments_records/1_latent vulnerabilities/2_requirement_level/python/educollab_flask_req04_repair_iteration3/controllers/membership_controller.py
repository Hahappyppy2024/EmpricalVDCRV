import sqlite3

from flask import Blueprint, jsonify, redirect, render_template, request, url_for

from models.course_repo import get_course_by_id
from models.membership_repo import (
    ALLOWED_ROLES,
    add_membership,
    get_membership_by_id,
    list_members,
    list_memberships_for_user,
    remove_membership,
    update_membership_role,
)
from models.user_repo import list_users
from utils.auth import (
    get_current_user,
    login_required,
    require_teacher_or_admin,
)


membership_bp = Blueprint('memberships', __name__)


def _membership_to_dict(membership):
    if membership is None:
        return None
    payload = {
        'membership_id': membership['membership_id'],
        'course_id': membership['course_id'],
        'user_id': membership['user_id'],
        'role_in_course': membership['role_in_course'],
        'created_at': membership['created_at'],
    }
    for field in ('username', 'display_name', 'course_title'):
        if field in membership.keys():
            payload[field] = membership[field]
    return payload


@membership_bp.route('/courses/<int:course_id>/members/new', methods=['GET'])
@login_required
@require_teacher_or_admin
def new_member_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'memberships/new.html',
        course=course,
        users=list_users(),
        roles=sorted(ALLOWED_ROLES),
        current_user=get_current_user(),
    )


@membership_bp.route('/courses/<int:course_id>/members', methods=['POST'])
@login_required
@require_teacher_or_admin
def add_member_submit(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404

    user_id = request.form.get('user_id', '').strip()
    role_in_course = request.form.get('role_in_course', '').strip()
    if not user_id.isdigit() or role_in_course not in ALLOWED_ROLES:
        return render_template(
            'memberships/new.html',
            course=course,
            users=list_users(),
            roles=sorted(ALLOWED_ROLES),
            error='Valid user and role are required.',
            current_user=get_current_user(),
        ), 400

    try:
        add_membership(course_id, int(user_id), role_in_course)
    except sqlite3.IntegrityError:
        return render_template(
            'memberships/new.html',
            course=course,
            users=list_users(),
            roles=sorted(ALLOWED_ROLES),
            error='Membership already exists or target user is invalid.',
            current_user=get_current_user(),
        ), 400
    return redirect(url_for('memberships.list_members_page', course_id=course_id))


@membership_bp.route('/api/courses/<int:course_id>/members', methods=['POST'])
@login_required
@require_teacher_or_admin
def api_add_member(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    data = request.get_json(silent=True) or {}
    user_id = data.get('user_id')
    role_in_course = str(data.get('role_in_course', '')).strip()
    if not isinstance(user_id, int) or role_in_course not in ALLOWED_ROLES:
        return jsonify({'success': False, 'error': 'invalid_membership_payload'}), 400
    try:
        membership = add_membership(course_id, user_id, role_in_course)
    except sqlite3.IntegrityError:
        return jsonify({'success': False, 'error': 'membership_create_failed'}), 400
    return jsonify({'success': True, 'membership': _membership_to_dict(membership)}), 201


@membership_bp.route('/courses/<int:course_id>/members', methods=['GET'])
@login_required
def list_members_page(course_id):
    course = get_course_by_id(course_id)
    if course is None:
        return render_template('404.html', current_user=get_current_user()), 404
    return render_template(
        'memberships/list.html',
        course=course,
        members=list_members(course_id),
        roles=sorted(ALLOWED_ROLES),
        current_user=get_current_user(),
    )


@membership_bp.route('/api/courses/<int:course_id>/members', methods=['GET'])
@login_required
def api_list_members(course_id):
    if get_course_by_id(course_id) is None:
        return jsonify({'success': False, 'error': 'course_not_found'}), 404
    members = [_membership_to_dict(item) for item in list_members(course_id)]
    return jsonify({'success': True, 'members': members})


@membership_bp.route('/courses/<int:course_id>/members/<int:membership_id>', methods=['POST'])
@login_required
@require_teacher_or_admin
def update_member_role_submit(course_id, membership_id):
    course = get_course_by_id(course_id)
    membership = get_membership_by_id(membership_id)
    if course is None or membership is None or membership['course_id'] != course_id:
        return render_template('404.html', current_user=get_current_user()), 404
    role_in_course = request.form.get('role_in_course', '').strip()
    if role_in_course not in ALLOWED_ROLES:
        return redirect(url_for('memberships.list_members_page', course_id=course_id))
    update_membership_role(membership_id, role_in_course)
    return redirect(url_for('memberships.list_members_page', course_id=course_id))


@membership_bp.route('/api/courses/<int:course_id>/members/<int:membership_id>', methods=['PUT'])
@login_required
@require_teacher_or_admin
def api_update_member_role(course_id, membership_id):
    membership = get_membership_by_id(membership_id)
    if membership is None or membership['course_id'] != course_id:
        return jsonify({'success': False, 'error': 'membership_not_found'}), 404
    data = request.get_json(silent=True) or {}
    role_in_course = str(data.get('role_in_course', '')).strip()
    if role_in_course not in ALLOWED_ROLES:
        return jsonify({'success': False, 'error': 'invalid_role'}), 400
    membership = update_membership_role(membership_id, role_in_course)
    return jsonify({'success': True, 'membership': _membership_to_dict(membership)})


@membership_bp.route('/courses/<int:course_id>/members/<int:membership_id>/delete', methods=['POST'])
@login_required
@require_teacher_or_admin
def remove_member_submit(course_id, membership_id):
    membership = get_membership_by_id(membership_id)
    if membership is None or membership['course_id'] != course_id:
        return render_template('404.html', current_user=get_current_user()), 404
    remove_membership(membership_id)
    return redirect(url_for('memberships.list_members_page', course_id=course_id))


@membership_bp.route('/api/courses/<int:course_id>/members/<int:membership_id>', methods=['DELETE'])
@login_required
@require_teacher_or_admin
def api_remove_member(course_id, membership_id):
    membership = get_membership_by_id(membership_id)
    if membership is None or membership['course_id'] != course_id:
        return jsonify({'success': False, 'error': 'membership_not_found'}), 404
    remove_membership(membership_id)
    return jsonify({'success': True, 'deleted_membership_id': membership_id})


@membership_bp.route('/memberships', methods=['GET'])
@login_required
def my_memberships_page():
    return render_template(
        'memberships/my.html',
        memberships=list_memberships_for_user(get_current_user()['user_id']),
        current_user=get_current_user(),
    )


@membership_bp.route('/api/memberships', methods=['GET'])
@login_required
def api_my_memberships():
    items = [_membership_to_dict(item) for item in list_memberships_for_user(get_current_user()['user_id'])]
    return jsonify({'success': True, 'memberships': items})
