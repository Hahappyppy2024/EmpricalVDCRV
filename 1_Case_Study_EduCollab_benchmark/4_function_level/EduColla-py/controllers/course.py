from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
from models.course import CourseRepository
from models.invite import InviteTokenRepository
from decorators import login_required, role_required

course_bp = Blueprint("course", __name__)

STAFF_ROLES = ["admin", "teacher", "assistant", "senior-assistant"]


# ============================================================
# HTML Routes
# ============================================================

@course_bp.route("/courses", methods=["GET"])
@login_required
def list_courses():
    courses = CourseRepository.list_all()
    return render_template("courses/list.html", courses=courses)


@course_bp.route("/courses/new", methods=["GET", "POST"])
@login_required
def new_course():
    if request.method == "GET":
        # pick ONE template; adjust if your project uses a different one
        return render_template("courses/form.html", course=None)

    data = request.form
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()

    if not title:
        return render_template("courses/form.html", course=None, error="title required"), 400

    course_id = CourseRepository.create(
        title=title,
        description=description,
        created_by=session["user_id"],
    )
    return redirect(url_for("course.get_course", course_id=course_id))


@course_bp.route("/courses/<int:course_id>", methods=["GET"])
@login_required
def get_course(course_id):
    course = CourseRepository.get(course_id)
    if not course:
        return render_template("404.html"), 404

    members = CourseRepository.get_members(course_id)
    return render_template("courses/detail.html", course=course, members=members)


@course_bp.route("/courses/<int:course_id>/edit", methods=["GET"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def edit_course(course_id):
    course = CourseRepository.get(course_id)
    if not course:
        return render_template("404.html"), 404
    return render_template("courses/form.html", course=course)


@course_bp.route("/courses/<int:course_id>", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def update_course_html(course_id):
    data = request.form
    CourseRepository.update(course_id, data.get("title"), data.get("description"))
    return redirect(url_for("course.get_course", course_id=course_id))


@course_bp.route("/courses/<int:course_id>/delete", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin"])
def delete_course_html(course_id):
    CourseRepository.delete(course_id)
    return redirect(url_for("course.list_courses"))


# Memberships (HTML)
@course_bp.route("/courses/<int:course_id>/members", methods=["GET"])
@login_required
def list_members(course_id):
    members = CourseRepository.get_members(course_id)
    return render_template("courses/members.html", members=members, course_id=course_id)


@course_bp.route("/courses/<int:course_id>/members/new", methods=["GET"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def add_member_form(course_id):
    return render_template("courses/add_member.html", course_id=course_id)


@course_bp.route("/courses/<int:course_id>/members", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def add_member_html(course_id):
    data = request.form
    CourseRepository.add_member(course_id, data.get("user_id"), data.get("role_in_course") or data.get("role"))
    return redirect(url_for("course.list_members", course_id=course_id))


@course_bp.route("/courses/<int:course_id>/members/<int:membership_id>/delete", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def remove_member_html(course_id, membership_id):
    CourseRepository.remove_member(course_id, membership_id)
    return redirect(url_for("course.list_members", course_id=course_id))


@course_bp.route("/memberships", methods=["GET"])
@login_required
def my_memberships():
    memberships = CourseRepository.get_user_memberships(session["user_id"])
    return render_template("courses/memberships.html", memberships=memberships)


# ============================================================
# API Routes (JSON)
# ============================================================

@course_bp.route("/api/courses", methods=["GET"])
@login_required
def api_list_courses():
    courses = CourseRepository.list_all()
    return jsonify([dict(c) for c in courses]), 200


@course_bp.route("/api/courses", methods=["POST"])
@login_required
def api_create_course():
    # data = request.get_json(silent=True) or {}#R10_exception__test_exceptional_conditions.md::test_api_malformed_json_returns_4xx_and_no_leak
    data=request
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()

    if not title:
        return jsonify({"error": "title required"}), 400

    course_id = CourseRepository.create(
        title=title,
        description=description,
        created_by=session["user_id"],
    )
    return jsonify({"course_id": course_id}), 201


@course_bp.route("/api/courses/<int:course_id>", methods=["GET"])
@login_required
def api_get_course(course_id):
    course = CourseRepository.get(course_id)
    if not course:
        return jsonify({"error": "Not found"}), 404

    members = CourseRepository.get_members(course_id)
    return jsonify({"course": dict(course), "members": [dict(m) for m in members]}), 200


@course_bp.route("/api/courses/<int:course_id>", methods=["PUT"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def api_update_course(course_id):
    data = request.get_json(silent=True) or {}
    CourseRepository.update(course_id, data.get("title"), data.get("description"))
    return jsonify({"message": "Updated"}), 200


@course_bp.route("/api/courses/<int:course_id>", methods=["DELETE"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin"])
def api_delete_course(course_id):
    CourseRepository.delete(course_id)
    return jsonify({"message": "Deleted"}), 200


# Memberships (API)
@course_bp.route("/api/courses/<int:course_id>/members", methods=["GET"])
@login_required
def api_list_members(course_id):
    members = CourseRepository.get_members(course_id)
    return jsonify([dict(m) for m in members]), 200


@course_bp.route("/api/courses/<int:course_id>/members", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def api_add_member(course_id):
    data = request.get_json(silent=True) or {}
    user_id = data.get("user_id")
    role = data.get("role_in_course") or data.get("role")
    if not user_id or not role:
        return jsonify({"error": "user_id and role_in_course required"}), 400

    CourseRepository.add_member(course_id, user_id, role)
    return jsonify({"message": "Member added"}), 201


@course_bp.route("/api/courses/<int:course_id>/members/<int:membership_id>", methods=["PUT"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def api_update_member_role(course_id, membership_id):
    data = request.get_json(silent=True) or {}
    role = data.get("role_in_course")
    if not role:
        return jsonify({"error": "role_in_course required"}), 400

    CourseRepository.update_member_role(course_id, membership_id, role)
    return jsonify({"message": "Role updated"}), 200

@course_bp.post("/courses/<int:course_id>/members/<int:membership_id>/remove")
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def remove_member(course_id, membership_id):
    CourseRepository.remove_member(course_id, membership_id)
    return redirect(url_for("course.course_members", course_id=course_id))


@course_bp.route("/api/courses/<int:course_id>/members/<int:membership_id>", methods=["DELETE"])
@login_required
@role_required(course_id_kw="course_id", roles=["admin", "teacher"])
def api_remove_member(course_id, membership_id):
    CourseRepository.remove_member(course_id, membership_id)
    return jsonify({"message": "Member removed"}), 200


@course_bp.route("/api/memberships", methods=["GET"])
@login_required
def api_my_memberships():
    memberships = CourseRepository.get_user_memberships(session["user_id"])
    return jsonify([dict(m) for m in memberships]), 200


# ------------------------------------------------------------
# Invite links (token-based join)
# ------------------------------------------------------------

@course_bp.route("/api/courses/<int:course_id>/invites", methods=["POST"])
@login_required
@role_required(course_id_kw="course_id", roles=STAFF_ROLES)
def api_create_invite(course_id):
    data = request.get_json(silent=True) or {}
    role_in_course = data.get("role_in_course", "student")
    ttl_minutes = int(data.get("ttl_minutes", 60))

    inv = InviteTokenRepository.create(
        course_id=course_id,
        created_by=session["user_id"],
        role_in_course=role_in_course,
        ttl_minutes=ttl_minutes,
    )
    invite_link = url_for("course.join_with_token_web", token=inv["token"], _external=False)
    return jsonify(
        {
            "invite_id": inv["invite_id"],
            "invite_link": invite_link,
            "expires_at": inv["expires_at"],
            "role_in_course": inv["role_in_course"],
            "course_id": inv["course_id"],
        }
    ), 200


@course_bp.route("/api/join", methods=["POST"])
@login_required
def api_join_with_token():
    data = request.get_json(silent=True) or {}
    token = (data.get("token") or "").strip()
    if not token:
        return jsonify({"error": "token required"}), 400

    inv = InviteTokenRepository.get_valid_by_token(token)
    if not inv:
        return jsonify({"error": "invalid or expired token"}), 403

    CourseRepository.add_member(inv["course_id"], session["user_id"], inv["role_in_course"])
    InviteTokenRepository.mark_used(inv["invite_id"], session["user_id"])
    return jsonify({"joined": True, "course_id": inv["course_id"], "role_in_course": inv["role_in_course"]}), 200


@course_bp.route("/join", methods=["GET"])
@login_required
def join_with_token_web():
    token = (request.args.get("token") or "").strip()
    if not token:
        return "token required", 400

    inv = InviteTokenRepository.get_valid_by_token(token)
    if not inv:
        return "invalid or expired token", 403

    CourseRepository.add_member(inv["course_id"], session["user_id"], inv["role_in_course"])
    InviteTokenRepository.mark_used(inv["invite_id"], session["user_id"])
    return redirect(url_for("course.get_course", course_id=inv["course_id"]))