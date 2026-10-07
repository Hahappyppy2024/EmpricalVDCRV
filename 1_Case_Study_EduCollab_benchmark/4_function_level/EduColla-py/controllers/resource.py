import os, io, csv, zipfile, shutil
from flask import Blueprint, request, jsonify, send_file, session, current_app
from decorators import login_required, role_required
from database import get_db_connection

resource_bp = Blueprint("resource", __name__)

STAFF = ['admin', 'teacher', 'assistant', 'senior-assistant']


@resource_bp.route("/courses/<int:course_id>/materials/upload-zip", methods=["POST"])
@role_required(course_id_kw="course_id", roles=STAFF)
def materials_upload_zip(course_id):
    if "file" not in request.files:
        return jsonify({"error": "No file"}), 400
    f = request.files["file"]
    if not f.filename.lower().endswith(".zip"):
        return jsonify({"error": "Only .zip is allowed"}), 415

    base_dir = os.path.join(current_app.config["UPLOAD_FOLDER"], "materials", str(course_id))
    archives_dir = os.path.join(base_dir, "archives")
    extracted_root = os.path.join(base_dir, "extracted")
    os.makedirs(archives_dir, exist_ok=True)
    os.makedirs(extracted_root, exist_ok=True)

    # save archive
    archive_name = f.filename
    archive_path = os.path.join(archives_dir, archive_name)
    f.save(archive_path)

    # extracted dir per archive (simple)
    extracted_dir = os.path.join(extracted_root, os.path.splitext(archive_name)[0])
    if os.path.exists(extracted_dir):
        shutil.rmtree(extracted_dir)
    os.makedirs(extracted_dir, exist_ok=True)

    # safe unzip (zip slip protection)
    with zipfile.ZipFile(archive_path) as zf:
        for member in zf.infolist():
            target = os.path.abspath(os.path.join(extracted_dir, member.filename))
            if not target.startswith(os.path.abspath(extracted_dir) + os.sep):
                return jsonify({"error": "ZipSlip detected"}), 400
        zf.extractall(extracted_dir)

    # write DB
    conn = get_db_connection()
    cur = conn.execute(
        "INSERT INTO material_archive (course_id, uploaded_by, original_name, storage_path, extracted_dir) VALUES (?,?,?,?,?)",
        (course_id, session["user_id"], archive_name,
         os.path.relpath(archive_path, current_app.config["UPLOAD_FOLDER"]),
         os.path.relpath(extracted_dir, current_app.config["UPLOAD_FOLDER"]))
    )
    conn.commit()
    archive_id = cur.lastrowid
    conn.close()

    return jsonify({"archive_id": archive_id, "message": "extracted successfully"}), 201


@resource_bp.route("/courses/<int:course_id>/materials/<int:archive_id>/files", methods=["GET"])
@login_required
def materials_list_files(course_id, archive_id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM material_archive WHERE archive_id=? AND course_id=?",
                       (archive_id, course_id)).fetchone()
    conn.close()
    if not row:
        return jsonify({"error": "Not found"}), 404

    extracted_dir = os.path.join(current_app.config["UPLOAD_FOLDER"], row["extracted_dir"])
    files = []
    for root, _, filenames in os.walk(extracted_dir):
        for name in filenames:
            rel = os.path.relpath(os.path.join(root, name), extracted_dir).replace("\\", "/")
            files.append({"name": rel})
    return jsonify({"files": files}), 200


@resource_bp.route("/courses/<int:course_id>/materials/<int:archive_id>/download.zip", methods=["GET"])
@login_required
def materials_download_zip(course_id, archive_id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM material_archive WHERE archive_id=? AND course_id=?",
                       (archive_id, course_id)).fetchone()
    conn.close()
    if not row:
        return jsonify({"error": "Not found"}), 404

    extracted_dir = os.path.join(current_app.config["UPLOAD_FOLDER"], row["extracted_dir"])
    bio = io.BytesIO()
    with zipfile.ZipFile(bio, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, _, filenames in os.walk(extracted_dir):
            for name in filenames:
                full = os.path.join(root, name)
                arc = os.path.relpath(full, extracted_dir).replace("\\", "/")
                zf.write(full, arc)
    bio.seek(0)
    return send_file(bio, mimetype="application/zip", as_attachment=True, download_name="materials.zip")


@resource_bp.route("/courses/<int:course_id>/members/export.csv", methods=["GET"])
@role_required(course_id_kw="course_id", roles=STAFF)
def export_members_csv(course_id):
    conn = get_db_connection()
    rows = conn.execute(
        "SELECT u.username, u.display_name, m.role_in_course "
        "FROM membership m JOIN user u ON u.user_id=m.user_id "
        "WHERE m.course_id=? ORDER BY u.username", (course_id,)
    ).fetchall()
    conn.close()

    bio = io.StringIO()
    w = csv.writer(bio)
    w.writerow(["username", "display_name", "role_in_course"])
    for r in rows:
        w.writerow([r["username"], r["display_name"], r["role_in_course"]])

    data = bio.getvalue().encode("utf-8")
    return send_file(io.BytesIO(data), mimetype="text/csv", as_attachment=True, download_name="members.csv")


@resource_bp.route("/courses/<int:course_id>/members/import.csv", methods=["POST"])
@role_required(course_id_kw="course_id", roles=STAFF)
def import_members_csv(course_id):
    if "file" not in request.files:
        return jsonify({"error": "No file"}), 400
    f = request.files["file"]
    text = f.read().decode("utf-8", errors="replace")
    reader = csv.DictReader(io.StringIO(text))

    imported = 0
    conn = get_db_connection()
    for row in reader:
        username = (row.get("username") or "").strip()
        display = (row.get("display_name") or username).strip()
        role = (row.get("role_in_course") or "student").strip()
        if not username:
            continue

        conn.execute(
            "INSERT OR IGNORE INTO user (username, password_hash, display_name) VALUES (?, ?, ?)",
            (username, "!", display)
        )
        u = conn.execute("SELECT user_id FROM user WHERE username=?", (username,)).fetchone()
        conn.execute(
            "INSERT OR REPLACE INTO membership (course_id, user_id, role_in_course) VALUES (?,?,?)",
            (course_id, u["user_id"], role)
        )
        imported += 1
    conn.commit()
    conn.close()
    return jsonify({"imported_count": imported}), 200


@resource_bp.route("/courses/<int:course_id>/assignments/<int:assignment_id>/grades.csv", methods=["GET"])
@role_required(course_id_kw="course_id", roles=STAFF)
def export_assignment_grades(course_id, assignment_id):
    conn = get_db_connection()
    rows = conn.execute(
        "SELECT u.username, u.display_name, s.grade "
        "FROM submission s JOIN user u ON u.user_id=s.student_id "
        "WHERE s.course_id=? AND s.assignment_id=?",
        (course_id, assignment_id)
    ).fetchall()
    conn.close()

    bio = io.StringIO()
    w = csv.writer(bio)
    w.writerow(["username", "display_name", "grade"])
    for r in rows:
        w.writerow([r["username"], r["display_name"], r["grade"]])

    data = bio.getvalue().encode("utf-8")
    return send_file(io.BytesIO(data), mimetype="text/csv", as_attachment=True, download_name="grades.csv")