# controllers/upload.py

from flask import (
    Blueprint,
    render_template,
    request,
    redirect,
    url_for,
    session,
    jsonify,
    send_from_directory,
    current_app,
)
from models.upload import UploadRepository
from decorators import login_required, role_required

up_bp = Blueprint("upload", __name__)

STAFF_ROLES = ["admin", "teacher", "assistant", "senior-assistant"]


# ============================================================
# HTML Routes
# ============================================================

@up_bp.route("/courses/<int:course_id>/uploads", methods=["GET"])
@login_required
def list_uploads(course_id):
    """HTML: list uploads for a course (needed by templates: url_for('upload.list_uploads', ...))."""
    uploads = UploadRepository.list_by_course(course_id)
    return render_template("uploads/list.html", uploads=uploads, course_id=course_id)


@up_bp.route("/courses/<int:course_id>/uploads/new", methods=["GET"])
@role_required(course_id_kw="course_id", roles=STAFF_ROLES)
def upload_form(course_id):
    """HTML: upload form (staff only)."""
    return render_template("uploads/form.html", course_id=course_id)


# ============================================================
# Shared Upload Handler (HTML + API)
# ============================================================

@up_bp.route("/courses/<int:course_id>/uploads", methods=["POST"])
@up_bp.route("/api/courses/<int:course_id>/uploads", methods=["POST"])
@role_required(course_id_kw="course_id", roles=STAFF_ROLES)
def upload_file(course_id):
    """
    POST upload:
      - HTML: expects multipart form-data, redirects back to uploads list
      - API : expects multipart form-data, returns JSON
    """
    is_api = request.path.startswith("/api/")


    #/R10_exception__test_exceptional_conditions.md::test_api_malformed_json_returns_4xx_and_no_leak -
    if "file" not in request.files:
        if is_api:
            return jsonify({"error": "No file"}), 400
        return "No file", 400
    #/R10_exception__test_exceptional_conditions.md::test_api_malformed_json_returns_4xx_and_no_leak -


    file = request.files["file"]
    # R08 test_api_upload_rejects_empty_filename -
    if not file or not getattr(file, "filename", ""):
        if is_api:
            return jsonify({"error": "No selected file"}), 400
        return "No selected file", 400
    # R08 test_api_upload_rejects_empty_filename -


    upload_id = UploadRepository.save_file(file, course_id, session.get("user_id"))
    if upload_id is None:
        if is_api:
            return jsonify({"error": "Upload failed"}), 500
        return "Upload failed", 500

    if is_api:
        return jsonify({"upload_id": upload_id}), 201

    return redirect(url_for("upload.list_uploads", course_id=course_id))


@up_bp.route("/courses/<int:course_id>/uploads/<int:upload_id>/download", methods=["GET"])
@login_required
def download_upload(course_id, upload_id):
    """HTML: download a file (requires login)."""
    upload = UploadRepository.get(upload_id)
    if not upload:
        return "Not found", 404

    # Ensure upload belongs to the course in URL
    if int(upload["course_id"]) != int(course_id):
        return "Not found", 404

    directory = current_app.config["UPLOAD_FOLDER"]
    # storage_path is relative: "<course_id>/<uuid>.<ext>" (or similar)
    return send_from_directory(
        directory,
        upload["storage_path"],
        as_attachment=True,
        download_name=upload["original_name"],
    )


@up_bp.route("/courses/<int:course_id>/uploads/<int:upload_id>/delete", methods=["POST"])
@role_required(course_id_kw="course_id", roles=STAFF_ROLES)
def delete_upload(course_id, upload_id):
    """HTML: delete a file (staff only)."""
    upload = UploadRepository.get(upload_id)
    if not upload or int(upload["course_id"]) != int(course_id):
        return "Not found", 404

    UploadRepository.delete(upload_id)
    return redirect(url_for("upload.list_uploads", course_id=course_id))


# ============================================================
# API Routes (JSON)
# ============================================================

@up_bp.route("/api/courses/<int:course_id>/uploads", methods=["GET"])
@login_required
def api_list_uploads(course_id):
    """API: list uploads for a course."""
    uploads = UploadRepository.list_by_course(course_id)
    return jsonify([dict(u) for u in uploads]), 200


@up_bp.route("/api/courses/<int:course_id>/uploads/<int:upload_id>", methods=["DELETE"])
@role_required(course_id_kw="course_id", roles=STAFF_ROLES)
def api_delete_upload(course_id, upload_id):
    """API: delete upload (staff only)."""
    upload = UploadRepository.get(upload_id)
    if not upload:
        return jsonify({"error": "Not found"}), 404

    if int(upload["course_id"]) != int(course_id):
        return jsonify({"error": "Not found"}), 404

    UploadRepository.delete(upload_id)
    return jsonify({"message": "Deleted"}), 200