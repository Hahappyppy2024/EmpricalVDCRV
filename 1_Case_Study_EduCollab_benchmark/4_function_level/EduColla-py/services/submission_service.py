import os
import io
import csv
import zipfile
from datetime import datetime
from flask import current_app, send_file
from database import get_db_connection


class SubmissionService:
    @staticmethod
    def get_upload_folder():
        return current_app.config.get("UPLOAD_FOLDER")

    @staticmethod
    def unzip_submission_attachment(submission_id: int, overwrite: bool = True):
        """
        Unzip a single submission's zip attachment into:
          UPLOAD_FOLDER/<zip_basename>/
        Update submission.extracted_path in DB.
        """
        conn = get_db_connection()
        sub = conn.execute("SELECT * FROM submission WHERE submission_id=?", (submission_id,)).fetchone()
        if not sub:
            conn.close()
            return False, "Submission not found"

        file_path = sub.get("file_path") if hasattr(sub, "get") else sub["file_path"]
        if not file_path or not str(file_path).lower().endswith(".zip"):
            conn.close()
            return False, "No valid zip file attached"

        base_dir = SubmissionService.get_upload_folder()
        zip_path = os.path.join(base_dir, file_path)
        if not os.path.exists(zip_path):
            conn.close()
            return False, "Zip file missing on server"

        extract_dir = os.path.join(base_dir, os.path.splitext(file_path)[0])
        os.makedirs(extract_dir, exist_ok=True)

        try:
            with zipfile.ZipFile(zip_path, "r") as zf:
                # overwrite=False: detect conflicts
                if not overwrite:
                    for member in zf.namelist():
                        target = os.path.join(extract_dir, member)
                        if os.path.exists(target):
                            conn.close()
                            return False, "Conflict: File exists and overwrite is disabled"

                # basic ZipSlip protection
                extract_abs = os.path.abspath(extract_dir)
                for member in zf.infolist():
                    target_abs = os.path.abspath(os.path.join(extract_dir, member.filename))
                    if not target_abs.startswith(extract_abs + os.sep) and target_abs != extract_abs:
                        conn.close()
                        return False, "ZipSlip detected"

                zf.extractall(extract_dir)

            conn.execute("UPDATE submission SET extracted_path=? WHERE submission_id=?", (extract_dir, submission_id))
            conn.commit()
            conn.close()
            return True, f"Successfully extracted to {extract_dir}"

        except zipfile.BadZipFile:
            conn.close()
            return False, "Bad Zip File"
        except Exception as e:
            conn.close()
            return False, str(e)


    @staticmethod
    def list_submission_extracted_files(submission_id: int):
        """
        List extracted files under submission.extracted_path (relative paths).
        """
        conn = get_db_connection()
        sub = conn.execute("SELECT * FROM submission WHERE submission_id=?", (submission_id,)).fetchone()
        conn.close()
        if not sub or not sub["extracted_path"]:
            return []

        extract_dir = sub["extracted_path"]
        if not os.path.exists(extract_dir):
            return []

        file_list = []
        for root, _, files in os.walk(extract_dir):
            for fn in files:
                full_path = os.path.join(root, fn)
                rel_path = os.path.relpath(full_path, extract_dir).replace("\\", "/")
                file_list.append(rel_path)
        return file_list


    @staticmethod
    def export_assignment_submissions_zip(assignment_id: int):
        """
        Package all submissions' attached zip files for an assignment into one zip:
          UPLOAD_FOLDER/exports/assignment_<id>_submissions.zip
        Structure inside zip: <username>/<basename(file_path)>
        """
        conn = get_db_connection()
        asg = conn.execute("SELECT * FROM assignment WHERE assignment_id=?", (assignment_id,)).fetchone()
        if not asg:
            conn.close()
            return None

        subs = conn.execute("SELECT * FROM submission WHERE assignment_id=?", (assignment_id,)).fetchall()
        if not subs:
            conn.close()
            return None

        base_dir = SubmissionService.get_upload_folder()
        exports_dir = os.path.join(base_dir, "exports")
        os.makedirs(exports_dir, exist_ok=True)

        zip_name = f"assignment_{assignment_id}_submissions.zip"
        zip_path = os.path.join(exports_dir, zip_name)

        try:
            with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
                for sub in subs:
                    fp = sub["file_path"]
                    if not fp:
                        continue
                    src = os.path.join(base_dir, fp)
                    if not os.path.exists(src):
                        continue

                    user = conn.execute("SELECT * FROM user WHERE user_id=?", (sub["student_id"],)).fetchone()
                    username = user["username"] if user else f"user_{sub['student_id']}"
                    arcname = os.path.join(username, os.path.basename(fp)).replace("\\", "/")
                    zf.write(src, arcname)
            conn.close()
            return zip_path
        except Exception:
            conn.close()
            return None


    @staticmethod
    def download_exported_submissions_zip(assignment_id: int):
        """
        Return Flask send_file() response for the exported zip.
        """
        zip_path = SubmissionService.export_assignment_submissions_zip(assignment_id)
        if zip_path and os.path.exists(zip_path):
            return send_file(zip_path, as_attachment=True, download_name=os.path.basename(zip_path))
        return None


    @staticmethod
    def export_assignment_grades_csv(assignment_id: int):
        """
        Export grades CSV:
          UPLOAD_FOLDER/exports/assignment_<id>_grades.csv
        Columns: Student ID, Username, Score, Feedback, Submit Time
        """
        conn = get_db_connection()
        asg = conn.execute("SELECT * FROM assignment WHERE assignment_id=?", (assignment_id,)).fetchone()
        if not asg:
            conn.close()
            return None

        subs = conn.execute("SELECT * FROM submission WHERE assignment_id=?", (assignment_id,)).fetchall()

        base_dir = SubmissionService.get_upload_folder()
        exports_dir = os.path.join(base_dir, "exports")
        os.makedirs(exports_dir, exist_ok=True)

        csv_name = f"assignment_{assignment_id}_grades.csv"
        csv_path = os.path.join(exports_dir, csv_name)

        try:
            with open(csv_path, "w", newline="", encoding="utf-8") as f:
                w = csv.writer(f)
                w.writerow(["Student ID", "Username", "Score", "Feedback", "Submit Time"])
                for sub in subs:
                    user = conn.execute("SELECT * FROM user WHERE user_id=?", (sub["student_id"],)).fetchone()
                    username = user["username"] if user else "Unknown"
                    score = sub["score"] if sub["score"] is not None else 0
                    feedback = sub["feedback"] if sub["feedback"] else ""
                    submit_time = sub["submit_time"] if sub["submit_time"] else ""
                    w.writerow([sub["student_id"], username, score, feedback, submit_time])
            conn.close()
            return csv_path
        except Exception:
            conn.close()
            return None


    @staticmethod
    def import_assignment_grades_csv(file_path: str):
        """
        Import grades CSV and update each student's latest submission (by submit_time desc, else by submission_id desc).
        CSV requires columns: Student ID, Score, Feedback (optional)
        """
        if not os.path.exists(file_path):
            return 0, "File not found"

        conn = get_db_connection()
        updated = 0
        try:
            with open(file_path, "r", encoding="utf-8") as f:
                reader = csv.DictReader(f)
                for row in reader:
                    try:
                        student_id = int(row.get("Student ID"))
                        score = float(row.get("Score"))
                        feedback = row.get("Feedback", "")
                    except Exception:
                        continue

                    sub = conn.execute(
                        "SELECT * FROM submission WHERE student_id=? ORDER BY submit_time DESC, submission_id DESC LIMIT 1",
                        (student_id,),
                    ).fetchone()
                    if not sub:
                        continue

                    conn.execute(
                        "UPDATE submission SET score=?, feedback=? WHERE submission_id=?",
                        (score, feedback, sub["submission_id"]),
                    )
                    updated += 1

            conn.commit()
            conn.close()
            return updated, "Import successful"
        except Exception as e:
            conn.rollback()
            conn.close()
            return 0, str(e)