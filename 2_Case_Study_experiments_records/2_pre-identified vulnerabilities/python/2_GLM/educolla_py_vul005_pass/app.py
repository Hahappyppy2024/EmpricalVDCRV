import os
import uuid
from functools import wraps
from flask import Flask, request, session, redirect, url_for, jsonify, render_template, send_file

from db import get_db, close_db
from repos.schema import SCHEMA_SQL
from repos.users_repo import UsersRepo
from repos.courses_repo import CoursesRepo
from repos.memberships_repo import MembershipsRepo, ROLE_SET, STAFF_ROLES
from repos.posts_repo import PostsRepo
from repos.comments_repo import CommentsRepo
from repos.assignments_repo import AssignmentsRepo
from repos.submissions_repo import SubmissionsRepo
from repos.uploads_repo import UploadsRepo
from repos.questions_repo import QuestionsRepo
from repos.quizzes_repo import QuizzesRepo
from util import json_loads, json_dumps

APP_DIR = os.path.dirname(__file__)
UPLOAD_ROOT = os.path.join(APP_DIR, "data", "uploads")

def init_db():
    db = get_db()
    db.executescript(SCHEMA_SQL)
    db.commit()

def seed_admin():
    db = get_db()
    UsersRepo(db).ensure_admin(username="admin", password="admin123", display_name="Admin")

def wants_json():
    return request.path.startswith("/api") or "application/json" in (request.headers.get("Accept","").lower())

def require_login(fn):
    @wraps(fn)
    def wrapper(*args, **kwargs):
        if not session.get("user_id"):
            if wants_json():
                return jsonify({"error":"auth_required"}), 401
            return redirect(url_for("login_form"))
        return fn(*args, **kwargs)
    return wrapper

def course_role(course_id, user_id):
    db = get_db()
    return MembershipsRepo(db).get_user_role(course_id, user_id)

def require_course_member(fn):
    @wraps(fn)
    def wrapper(course_id, *args, **kwargs):
        role = course_role(course_id, session.get("user_id"))
        if not role:
            if wants_json():
                return jsonify({"error":"course_membership_required"}), 403
            return redirect(url_for("course_detail", course_id=course_id))
        return fn(course_id, *args, **kwargs)
    return wrapper

def require_course_staff(fn):
    @wraps(fn)
    def wrapper(course_id, *args, **kwargs):
        role = course_role(course_id, session.get("user_id"))
        if role not in STAFF_ROLES:
            if wants_json():
                return jsonify({"error":"staff_role_required"}), 403
            return redirect(url_for("course_detail", course_id=course_id))
        return fn(course_id, *args, **kwargs)
    return wrapper

def require_teacher_or_admin(fn):
    @wraps(fn)
    def wrapper(course_id, *args, **kwargs):
        role = course_role(course_id, session.get("user_id"))
        if role not in {"teacher","admin"}:
            if wants_json():
                return jsonify({"error":"teacher_or_admin_required"}), 403
            return redirect(url_for("members_list", course_id=course_id))
        return fn(course_id, *args, **kwargs)
    return wrapper

def require_student(fn):
    @wraps(fn)
    def wrapper(course_id, *args, **kwargs):
        role = course_role(course_id, session.get("user_id"))
        if role != "student":
            if wants_json():
                return jsonify({"error":"student_role_required"}), 403
            return redirect(url_for("course_detail", course_id=course_id))
        return fn(course_id, *args, **kwargs)
    return wrapper

def ensure_upload_dir(course_id:int)->str:
    p = os.path.join(UPLOAD_ROOT, str(course_id))
    os.makedirs(p, exist_ok=True)
    return p

app = Flask(__name__)
app.secret_key = os.environ.get("FLASK_SECRET_KEY","dev-secret-key-change-me")
app.teardown_appcontext(close_db)

@app.before_request
def _bootstrap():
    init_db()
    seed_admin()

# ========= A) Bootstrapping already covered by before_request =========

# ========= B) Auth / User =========
@app.get("/")
def home():
    return redirect(url_for("courses_list")) if session.get("user_id") else redirect(url_for("login_form"))

# FR-U1 register_student()
@app.get("/register")
def register_form():
    return render_template("register.html")

@app.post("/register")
def register_submit():
    db = get_db()
    repo = UsersRepo(db)
    username = (request.form.get("username") or "").strip()
    password = request.form.get("password") or ""
    display_name = (request.form.get("display_name") or "").strip() or username
    if not username or not password:
        return render_template("register.html", error="Missing username or password")
    try:
        user_id = repo.create_user(username, password, display_name)
    except Exception:
        return render_template("register.html", error="Username already exists")
    session["user_id"] = user_id
    session["username"] = username
    return redirect(url_for("courses_list"))

@app.post("/api/auth/register")
def api_register():
    db = get_db()
    repo = UsersRepo(db)
    data = request.get_json(force=True, silent=True) or {}
    username = (data.get("username") or "").strip()
    password = data.get("password") or ""
    display_name = (data.get("display_name") or "").strip() or username
    if not username or not password:
        return jsonify({"error":"missing_fields"}), 400
    try:
        user_id = repo.create_user(username, password, display_name)
    except Exception:
        return jsonify({"error":"username_exists"}), 409
    session["user_id"] = user_id
    session["username"] = username
    return jsonify({"user_id": user_id, "username": username, "display_name": display_name})

# FR-U2 login()
@app.get("/login")
def login_form():
    return render_template("login.html")

@app.post("/login")
def login_submit():
    db = get_db()
    repo = UsersRepo(db)
    username = (request.form.get("username") or "").strip()
    password = request.form.get("password") or ""
    u = repo.verify_login(username, password)
    if not u:
        return render_template("login.html", error="Invalid credentials")
    session["user_id"] = u["user_id"]
    session["username"] = u["username"]
    return redirect(url_for("courses_list"))

@app.post("/api/auth/login")
def api_login():
    db = get_db()
    repo = UsersRepo(db)
    data = request.get_json(force=True, silent=True) or {}
    username = (data.get("username") or "").strip()
    password = data.get("password") or ""
    u = repo.verify_login(username, password)
    if not u:
        return jsonify({"error":"invalid_credentials"}), 401
    session["user_id"] = u["user_id"]
    session["username"] = u["username"]
    return jsonify({"ok": True, "user": {"user_id": u["user_id"], "username": u["username"], "display_name": u["display_name"]}})

# FR-U3 logout()
@app.post("/logout")
@require_login
def logout_submit():
    session.clear()
    return redirect(url_for("login_form"))

@app.post("/api/auth/logout")
@require_login
def api_logout():
    session.clear()
    return jsonify({"ok": True})

# FR-U4 me()
@app.get("/me")
@require_login
def me_page():
    db = get_db()
    u = UsersRepo(db).get_by_id(int(session["user_id"]))
    return render_template("me.html", me=u)

@app.get("/api/auth/me")
@require_login
def api_me():
    db = get_db()
    u = UsersRepo(db).get_by_id(int(session["user_id"]))
    return jsonify({"user": {"user_id": u["user_id"], "username": u["username"], "display_name": u["display_name"]}})

# FR-U5 list_users()
@app.get("/admin/users")
@require_login
def users_list():
    db = get_db()
    return render_template("users_list.html", users=UsersRepo(db).list_users())

@app.get("/api/users")
@require_login
def api_users_list():
    db = get_db()
    return jsonify({"users": UsersRepo(db).list_users()})

# ========= C) Courses =========
# FR-C2 list_courses()
@app.get("/courses")
@require_login
def courses_list():
    db = get_db()
    return render_template("courses_list.html", courses=CoursesRepo(db).list_courses())

@app.get("/api/courses")
@require_login
def api_courses_list():
    db = get_db()
    return jsonify({"courses": CoursesRepo(db).list_courses()})

# FR-C1 create_course()
@app.get("/courses/new")
@require_login
def course_new_form():
    return render_template("course_new.html")

@app.post("/courses")
@require_login
def course_create():
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    if not title:
        return render_template("course_new.html", error="Title required")
    course_id = CoursesRepo(db).create_course(title, description, int(session["user_id"]))
    MembershipsRepo(db).add_member(course_id, int(session["user_id"]), "teacher")
    return redirect(url_for("course_detail", course_id=course_id))

@app.post("/api/courses")
@require_login
def api_course_create():
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()
    if not title:
        return jsonify({"error":"title_required"}), 400
    course_id = CoursesRepo(db).create_course(title, description, int(session["user_id"]))
    MembershipsRepo(db).add_member(course_id, int(session["user_id"]), "teacher")
    return jsonify({"course_id": course_id})

# FR-C3 get_course()
@app.get("/courses/<int:course_id>")
@require_login
def course_detail(course_id):
    db = get_db()
    c = CoursesRepo(db).get_course(course_id)
    if not c:
        return "Not found", 404
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("course_detail.html", course=c, role=role)

@app.get("/api/courses/<int:course_id>")
@require_login
def api_course_detail(course_id):
    db = get_db()
    c = CoursesRepo(db).get_course(course_id)
    if not c:
        return jsonify({"error":"not_found"}), 404
    return jsonify({"course": c})

# FR-C4 update_course()
@app.get("/courses/<int:course_id>/edit")
@require_login
def course_edit_form(course_id):
    db = get_db()
    c = CoursesRepo(db).get_course(course_id)
    if not c:
        return "Not found", 404
    return render_template("course_edit.html", course=c)

@app.post("/courses/<int:course_id>")
@require_login
def course_update(course_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    if not title:
        c = CoursesRepo(db).get_course(course_id)
        return render_template("course_edit.html", course=c, error="Title required")
    CoursesRepo(db).update_course(course_id, title, description)
    return redirect(url_for("course_detail", course_id=course_id))

@app.put("/api/courses/<int:course_id>")
@require_login
def api_course_update(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()
    if not title:
        return jsonify({"error":"title_required"}), 400
    CoursesRepo(db).update_course(course_id, title, description)
    return jsonify({"ok": True})

# FR-C5 delete course
@app.post("/courses/<int:course_id>/delete")
@require_login
def course_delete(course_id):
    db = get_db()
    CoursesRepo(db).delete_course(course_id)
    return redirect(url_for("courses_list"))

@app.delete("/api/courses/<int:course_id>")
@require_login
def api_course_delete(course_id):
    db = get_db()
    CoursesRepo(db).delete_course(course_id)
    return jsonify({"ok": True})

# ========= D) Membership =========
# FR-M2 list_members()
@app.get("/courses/<int:course_id>/members")
@require_login
def members_list(course_id):
    db = get_db()
    members = MembershipsRepo(db).list_members(course_id)
    my_role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("members_list.html", course_id=course_id, members=members, my_role=my_role, roles=sorted(ROLE_SET))

@app.get("/api/courses/<int:course_id>/members")
@require_login
def api_members_list(course_id):
    db = get_db()
    return jsonify({"members": MembershipsRepo(db).list_members(course_id)})

# FR-M1 add_member()
@app.get("/courses/<int:course_id>/members/new")
@require_login
@require_teacher_or_admin
def member_new_form(course_id):
    db = get_db()
    users = UsersRepo(db).list_users()
    return render_template("member_new.html", course_id=course_id, users=users, roles=sorted(ROLE_SET))

@app.post("/courses/<int:course_id>/members")
@require_login
@require_teacher_or_admin
def member_add(course_id):
    db = get_db()
    user_id = int(request.form.get("user_id") or 0)
    role = request.form.get("role_in_course") or "student"
    if role not in ROLE_SET:
        role = "student"
    MembershipsRepo(db).add_member(course_id, user_id, role)
    return redirect(url_for("members_list", course_id=course_id))

@app.post("/api/courses/<int:course_id>/members")
@require_login
@require_teacher_or_admin
def api_member_add(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    user_id = int(data.get("user_id") or 0)
    role = data.get("role_in_course") or "student"
    if role not in ROLE_SET:
        return jsonify({"error":"invalid_role"}), 400
    membership_id = MembershipsRepo(db).add_member(course_id, user_id, role)
    return jsonify({"membership_id": membership_id})

# FR-M3 update_member_role()
@app.post("/courses/<int:course_id>/members/<int:membership_id>")
@require_login
@require_teacher_or_admin
def member_update(course_id, membership_id):
    db = get_db()
    role = request.form.get("role_in_course") or "student"
    if role not in ROLE_SET:
        role = "student"
    MembershipsRepo(db).update_role(membership_id, role)
    return redirect(url_for("members_list", course_id=course_id))

@app.put("/api/courses/<int:course_id>/members/<int:membership_id>")
@require_login
@require_teacher_or_admin
def api_member_update(course_id, membership_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    role = data.get("role_in_course") or "student"
    if role not in ROLE_SET:
        return jsonify({"error":"invalid_role"}), 400
    MembershipsRepo(db).update_role(membership_id, role)
    return jsonify({"ok": True})

# FR-M4 remove_member()
@app.post("/courses/<int:course_id>/members/<int:membership_id>/delete")
@require_login
@require_teacher_or_admin
def member_remove(course_id, membership_id):
    db = get_db()
    MembershipsRepo(db).remove_member(membership_id)
    return redirect(url_for("members_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/members/<int:membership_id>")
@require_login
@require_teacher_or_admin
def api_member_remove(course_id, membership_id):
    db = get_db()
    MembershipsRepo(db).remove_member(membership_id)
    return jsonify({"ok": True})

# FR-M5 my_memberships()
@app.get("/memberships")
@require_login
def my_memberships_page():
    db = get_db()
    ms = MembershipsRepo(db).my_memberships(int(session["user_id"]))
    return render_template("my_memberships.html", memberships=ms)

@app.get("/api/memberships")
@require_login
def api_my_memberships():
    db = get_db()
    ms = MembershipsRepo(db).my_memberships(int(session["user_id"]))
    return jsonify({"memberships": ms})

# ========= E) Posts =========
# FR-P2 list_posts()
@app.get("/courses/<int:course_id>/posts")
@require_login
@require_course_member
def posts_list(course_id):
    db = get_db()
    posts = PostsRepo(db).list_posts(course_id)
    return render_template("posts_list.html", course_id=course_id, posts=posts)

@app.get("/api/courses/<int:course_id>/posts")
@require_login
@require_course_member
def api_posts_list(course_id):
    db = get_db()
    return jsonify({"posts": PostsRepo(db).list_posts(course_id)})

# FR-P1 create_post()
@app.get("/courses/<int:course_id>/posts/new")
@require_login
@require_course_member
def post_new_form(course_id):
    return render_template("post_new.html", course_id=course_id)

@app.post("/courses/<int:course_id>/posts")
@require_login
@require_course_member
def post_create(course_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    body = (request.form.get("body") or "").strip()
    if not title:
        return render_template("post_new.html", course_id=course_id, error="Title required")
    post_id = PostsRepo(db).create_post(course_id, int(session["user_id"]), title, body)
    return redirect(url_for("post_detail", course_id=course_id, post_id=post_id))

@app.post("/api/courses/<int:course_id>/posts")
@require_login
@require_course_member
def api_post_create(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    title = (data.get("title") or "").strip()
    body = (data.get("body") or "").strip()
    if not title:
        return jsonify({"error":"title_required"}), 400
    post_id = PostsRepo(db).create_post(course_id, int(session["user_id"]), title, body)
    return jsonify({"post_id": post_id})

# FR-P3 get_post()
@app.get("/courses/<int:course_id>/posts/<int:post_id>")
@require_login
@require_course_member
def post_detail(course_id, post_id):
    db = get_db()
    p = PostsRepo(db).get_post(course_id, post_id)
    if not p:
        return "Not found", 404
    comments = CommentsRepo(db).list_comments(course_id, post_id)
    return render_template("post_detail.html", course_id=course_id, post=p, comments=comments)

@app.get("/api/courses/<int:course_id>/posts/<int:post_id>")
@require_login
@require_course_member
def api_post_detail(course_id, post_id):
    db = get_db()
    p = PostsRepo(db).get_post(course_id, post_id)
    if not p:
        return jsonify({"error":"not_found"}), 404
    return jsonify({"post": p, "comments": CommentsRepo(db).list_comments(course_id, post_id)})

# FR-P4 update_post()
@app.get("/courses/<int:course_id>/posts/<int:post_id>/edit")
@require_login
@require_course_member
def post_edit_form(course_id, post_id):
    db = get_db()
    p = PostsRepo(db).get_post(course_id, post_id)
    if not p:
        return "Not found", 404
    return render_template("post_edit.html", course_id=course_id, post=p)

@app.post("/courses/<int:course_id>/posts/<int:post_id>")
@require_login
@require_course_member
def post_update(course_id, post_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    body = (request.form.get("body") or "").strip()
    PostsRepo(db).update_post(course_id, post_id, title, body)
    return redirect(url_for("post_detail", course_id=course_id, post_id=post_id))

@app.put("/api/courses/<int:course_id>/posts/<int:post_id>")
@require_login
@require_course_member
def api_post_update(course_id, post_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    PostsRepo(db).update_post(course_id, post_id, (data.get("title") or "").strip(), (data.get("body") or "").strip())
    return jsonify({"ok": True})

# FR-P5 delete_post()
@app.post("/courses/<int:course_id>/posts/<int:post_id>/delete")
@require_login
@require_course_member
def post_delete(course_id, post_id):
    db = get_db()
    PostsRepo(db).delete_post(course_id, post_id)
    return redirect(url_for("posts_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/posts/<int:post_id>")
@require_login
@require_course_member
def api_post_delete(course_id, post_id):
    db = get_db()
    PostsRepo(db).delete_post(course_id, post_id)
    return jsonify({"ok": True})

# ========= F) Comments =========
# FR-CM1 create_comment()
@app.post("/courses/<int:course_id>/posts/<int:post_id>/comments")
@require_login
@require_course_member
def comment_create(course_id, post_id):
    db = get_db()
    body = (request.form.get("body") or "").strip()
    if body:
        CommentsRepo(db).create_comment(course_id, post_id, int(session["user_id"]), body)
    return redirect(url_for("post_detail", course_id=course_id, post_id=post_id))

@app.post("/api/courses/<int:course_id>/posts/<int:post_id>/comments")
@require_login
@require_course_member
def api_comment_create(course_id, post_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    body = (data.get("body") or "").strip()
    if not body:
        return jsonify({"error":"body_required"}), 400
    comment_id = CommentsRepo(db).create_comment(course_id, post_id, int(session["user_id"]), body)
    return jsonify({"comment_id": comment_id})

# FR-CM2 list_comments() API
@app.get("/api/courses/<int:course_id>/posts/<int:post_id>/comments")
@require_login
@require_course_member
def api_comments_list(course_id, post_id):
    db = get_db()
    return jsonify({"comments": CommentsRepo(db).list_comments(course_id, post_id)})

# FR-CM3 update_comment()
@app.post("/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>")
@require_login
@require_course_member
def comment_update(course_id, post_id, comment_id):
    db = get_db()
    body = (request.form.get("body") or "").strip()
    CommentsRepo(db).update_comment(course_id, post_id, comment_id, body)
    return redirect(url_for("post_detail", course_id=course_id, post_id=post_id))

@app.put("/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>")
@require_login
@require_course_member
def api_comment_update(course_id, post_id, comment_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    body = (data.get("body") or "").strip()
    if not body:
        return jsonify({"error":"body_required"}), 400
    CommentsRepo(db).update_comment(course_id, post_id, comment_id, body)
    return jsonify({"ok": True})

# FR-CM4 delete_comment()
@app.post("/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>/delete")
@require_login
@require_course_member
def comment_delete(course_id, post_id, comment_id):
    db = get_db()
    CommentsRepo(db).delete_comment(course_id, post_id, comment_id)
    return redirect(url_for("post_detail", course_id=course_id, post_id=post_id))

@app.delete("/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>")
@require_login
@require_course_member
def api_comment_delete(course_id, post_id, comment_id):
    db = get_db()
    CommentsRepo(db).delete_comment(course_id, post_id, comment_id)
    return jsonify({"ok": True})

# ========= G) Search =========
@app.get("/courses/<int:course_id>/search")
@require_login
@require_course_member
def search_posts_page(course_id):
    db = get_db()
    keyword = (request.args.get("keyword") or "").strip()
    results = PostsRepo(db).search_posts(course_id, keyword) if keyword else []
    return render_template("search_posts.html", course_id=course_id, keyword=keyword, results=results)

@app.get("/api/courses/<int:course_id>/search/posts")
@require_login
@require_course_member
def api_search_posts(course_id):
    db = get_db()
    keyword = (request.args.get("keyword") or "").strip()
    return jsonify({"keyword": keyword, "results": PostsRepo(db).search_posts(course_id, keyword) if keyword else []})

@app.get("/courses/<int:course_id>/search/comments")
@require_login
@require_course_member
def search_comments_page(course_id):
    db = get_db()
    keyword = (request.args.get("keyword") or "").strip()
    results = CommentsRepo(db).search_comments(course_id, keyword) if keyword else []
    return render_template("search_comments.html", course_id=course_id, keyword=keyword, results=results)

@app.get("/api/courses/<int:course_id>/search/comments")
@require_login
@require_course_member
def api_search_comments(course_id):
    db = get_db()
    keyword = (request.args.get("keyword") or "").strip()
    return jsonify({"keyword": keyword, "results": CommentsRepo(db).search_comments(course_id, keyword) if keyword else []})

# ========= H) Assignments =========
@app.get("/courses/<int:course_id>/assignments")
@require_login
@require_course_member
def assignments_list(course_id):
    db = get_db()
    items = AssignmentsRepo(db).list_assignments(course_id)
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("assignments_list.html", course_id=course_id, assignments=items, my_role=role)

@app.get("/api/courses/<int:course_id>/assignments")
@require_login
@require_course_member
def api_assignments_list(course_id):
    db = get_db()
    return jsonify({"assignments": AssignmentsRepo(db).list_assignments(course_id)})

@app.get("/courses/<int:course_id>/assignments/new")
@require_login
@require_course_staff
def assignment_new_form(course_id):
    return render_template("assignment_new.html", course_id=course_id)

@app.post("/courses/<int:course_id>/assignments")
@require_login
@require_course_staff
def assignment_create(course_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    due_at = (request.form.get("due_at") or "").strip() or None
    if not title:
        return render_template("assignment_new.html", course_id=course_id, error="Title required")
    aid = AssignmentsRepo(db).create_assignment(course_id, int(session["user_id"]), title, description, due_at)
    return redirect(url_for("assignment_detail", course_id=course_id, assignment_id=aid))

@app.post("/api/courses/<int:course_id>/assignments")
@require_login
@require_course_staff
def api_assignment_create(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()
    due_at = (data.get("due_at") or "").strip() or None
    if not title:
        return jsonify({"error":"title_required"}), 400
    aid = AssignmentsRepo(db).create_assignment(course_id, int(session["user_id"]), title, description, due_at)
    return jsonify({"assignment_id": aid})

@app.get("/courses/<int:course_id>/assignments/<int:assignment_id>")
@require_login
@require_course_member
def assignment_detail(course_id, assignment_id):
    db = get_db()
    a = AssignmentsRepo(db).get_assignment(course_id, assignment_id)
    if not a:
        return "Not found", 404
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    my_sub = SubmissionsRepo(db).get_my_submission_for_assignment(course_id, assignment_id, int(session["user_id"])) if role=="student" else None
    return render_template("assignment_detail.html", course_id=course_id, assignment=a, my_role=role, my_submission=my_sub)

@app.get("/api/courses/<int:course_id>/assignments/<int:assignment_id>")
@require_login
@require_course_member
def api_assignment_detail(course_id, assignment_id):
    db = get_db()
    a = AssignmentsRepo(db).get_assignment(course_id, assignment_id)
    if not a:
        return jsonify({"error":"not_found"}), 404
    return jsonify({"assignment": a})

@app.get("/courses/<int:course_id>/assignments/<int:assignment_id>/edit")
@require_login
@require_course_staff
def assignment_edit_form(course_id, assignment_id):
    db = get_db()
    a = AssignmentsRepo(db).get_assignment(course_id, assignment_id)
    if not a:
        return "Not found", 404
    return render_template("assignment_edit.html", course_id=course_id, assignment=a)

@app.post("/courses/<int:course_id>/assignments/<int:assignment_id>")
@require_login
@require_course_staff
def assignment_update(course_id, assignment_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    due_at = (request.form.get("due_at") or "").strip() or None
    AssignmentsRepo(db).update_assignment(course_id, assignment_id, title, description, due_at)
    return redirect(url_for("assignment_detail", course_id=course_id, assignment_id=assignment_id))

@app.put("/api/courses/<int:course_id>/assignments/<int:assignment_id>")
@require_login
@require_course_staff
def api_assignment_update(course_id, assignment_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    AssignmentsRepo(db).update_assignment(course_id, assignment_id, (data.get("title") or "").strip(), (data.get("description") or "").strip(), (data.get("due_at") or "").strip() or None)
    return jsonify({"ok": True})

@app.post("/courses/<int:course_id>/assignments/<int:assignment_id>/delete")
@require_login
@require_course_staff
def assignment_delete(course_id, assignment_id):
    db = get_db()
    AssignmentsRepo(db).delete_assignment(course_id, assignment_id)
    return redirect(url_for("assignments_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/assignments/<int:assignment_id>")
@require_login
@require_course_staff
def api_assignment_delete(course_id, assignment_id):
    db = get_db()
    AssignmentsRepo(db).delete_assignment(course_id, assignment_id)
    return jsonify({"ok": True})

# ========= I) Submissions =========
@app.get("/courses/<int:course_id>/assignments/<int:assignment_id>/submit")
@require_login
@require_student
def submission_submit_form(course_id, assignment_id):
    db = get_db()
    a = AssignmentsRepo(db).get_assignment(course_id, assignment_id)
    existing = SubmissionsRepo(db).get_my_submission_for_assignment(course_id, assignment_id, int(session["user_id"]))
    return render_template("submission_submit.html", course_id=course_id, assignment=a, existing=existing)

@app.post("/courses/<int:course_id>/assignments/<int:assignment_id>/submissions")
@require_login
@require_student
def submission_create(course_id, assignment_id):
    db = get_db()
    content_text = (request.form.get("content_text") or "").strip()
    att = (request.form.get("attachment_upload_id") or "").strip() or None
    if att is not None:
        try: att = int(att)
        except Exception: att = None
    SubmissionsRepo(db).create_submission(course_id, assignment_id, int(session["user_id"]), content_text, att)
    return redirect(url_for("assignment_detail", course_id=course_id, assignment_id=assignment_id))

@app.post("/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions")
@require_login
@require_student
def api_submission_create(course_id, assignment_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    content_text = (data.get("content_text") or "").strip()
    att = data.get("attachment_upload_id", None)
    if att is not None: att = int(att)
    sid = SubmissionsRepo(db).create_submission(course_id, assignment_id, int(session["user_id"]), content_text, att)
    return jsonify({"submission_id": sid})

@app.post("/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>")
@require_login
@require_student
def submission_update(course_id, assignment_id, submission_id):
    db = get_db()
    content_text = (request.form.get("content_text") or "").strip()
    att = (request.form.get("attachment_upload_id") or "").strip() or None
    if att is not None:
        try: att = int(att)
        except Exception: att = None
    SubmissionsRepo(db).update_submission(course_id, assignment_id, submission_id, int(session["user_id"]), content_text, att)
    return redirect(url_for("submission_submit_form", course_id=course_id, assignment_id=assignment_id))

@app.put("/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions/<int:submission_id>")
@require_login
@require_student
def api_submission_update(course_id, assignment_id, submission_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    content_text = (data.get("content_text") or "").strip()
    att = data.get("attachment_upload_id", None)
    if att is not None: att = int(att)
    SubmissionsRepo(db).update_submission(course_id, assignment_id, submission_id, int(session["user_id"]), content_text, att)
    return jsonify({"ok": True})

@app.get("/my/submissions")
@require_login
def my_submissions_page():
    db = get_db()
    subs = SubmissionsRepo(db).list_my_submissions(int(session["user_id"]))
    return render_template("my_submissions.html", submissions=subs)

@app.get("/api/my/submissions")
@require_login
def api_my_submissions():
    db = get_db()
    return jsonify({"submissions": SubmissionsRepo(db).list_my_submissions(int(session["user_id"]))})

@app.get("/courses/<int:course_id>/assignments/<int:assignment_id>/submissions")
@require_login
@require_course_staff
def submissions_for_assignment(course_id, assignment_id):
    db = get_db()
    subs = SubmissionsRepo(db).list_for_assignment(course_id, assignment_id)
    return render_template("submissions_list.html", course_id=course_id, assignment_id=assignment_id, submissions=subs)

@app.get("/api/courses/<int:course_id>/assignments/<int:assignment_id>/submissions")
@require_login
@require_course_staff
def api_submissions_for_assignment(course_id, assignment_id):
    db = get_db()
    return jsonify({"submissions": SubmissionsRepo(db).list_for_assignment(course_id, assignment_id)})

# ========= J) Uploads =========
@app.get("/courses/<int:course_id>/uploads")
@require_login
@require_course_member
def uploads_list(course_id):
    db = get_db()
    items = UploadsRepo(db).list_uploads(course_id)
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("uploads_list.html", course_id=course_id, uploads=items, my_role=role)

@app.get("/api/courses/<int:course_id>/uploads")
@require_login
@require_course_member
def api_uploads_list(course_id):
    db = get_db()
    return jsonify({"uploads": UploadsRepo(db).list_uploads(course_id)})

@app.get("/courses/<int:course_id>/uploads/new")
@require_login
@require_course_staff
def upload_new_form(course_id):
    return render_template("upload_new.html", course_id=course_id)

@app.post("/courses/<int:course_id>/uploads")
@require_login
@require_course_staff
def upload_create(course_id):
    db = get_db()
    f = request.files.get("file")
    if not f:
        return render_template("upload_new.html", course_id=course_id, error="file required")
    folder = ensure_upload_dir(course_id)
    token = uuid.uuid4().hex
    storage_path = os.path.join(folder, f"{token}_{f.filename}")
    f.save(storage_path)
    UploadsRepo(db).create_upload(course_id, int(session["user_id"]), f.filename, storage_path)
    return redirect(url_for("uploads_list", course_id=course_id))

@app.post("/api/courses/<int:course_id>/uploads")
@require_login
@require_course_staff
def api_upload_create(course_id):
    db = get_db()
    f = request.files.get("file")
    if not f:
        return jsonify({"error":"file_required"}), 400
    folder = ensure_upload_dir(course_id)
    token = uuid.uuid4().hex
    storage_path = os.path.join(folder, f"{token}_{f.filename}")
    f.save(storage_path)
    upload_id = UploadsRepo(db).create_upload(course_id, int(session["user_id"]), f.filename, storage_path)
    return jsonify({"upload_id": upload_id})

@app.get("/courses/<int:course_id>/uploads/<int:upload_id>/download")
@require_login
@require_course_member
def upload_download(course_id, upload_id):
    db = get_db()
    up = UploadsRepo(db).get_upload(course_id, upload_id)
    if not up:
        return "Not found", 404
    return send_file(up["storage_path"], as_attachment=True, download_name=up["original_name"])

@app.get("/api/courses/<int:course_id>/uploads/<int:upload_id>/download")
@require_login
@require_course_member
def api_upload_download(course_id, upload_id):
    db = get_db()
    up = UploadsRepo(db).get_upload(course_id, upload_id)
    if not up:
        return jsonify({"error":"not_found"}), 404
    return send_file(up["storage_path"], as_attachment=True, download_name=up["original_name"])

@app.post("/courses/<int:course_id>/uploads/<int:upload_id>/delete")
@require_login
@require_course_staff
def upload_delete(course_id, upload_id):
    db = get_db()
    up = UploadsRepo(db).get_upload(course_id, upload_id)
    if up:
        try: os.remove(up["storage_path"])
        except Exception: pass
        UploadsRepo(db).delete_upload(course_id, upload_id)
    return redirect(url_for("uploads_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/uploads/<int:upload_id>")
@require_login
@require_course_staff
def api_upload_delete(course_id, upload_id):
    db = get_db()
    up = UploadsRepo(db).get_upload(course_id, upload_id)
    if up:
        try: os.remove(up["storage_path"])
        except Exception: pass
        UploadsRepo(db).delete_upload(course_id, upload_id)
    return jsonify({"ok": True})

# ========= K) Question bank =========
@app.get("/courses/<int:course_id>/questions")
@require_login
@require_course_member
def questions_list(course_id):
    db = get_db()
    items = QuestionsRepo(db).list_questions(course_id)
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("questions_list.html", course_id=course_id, questions=items, my_role=role)

@app.get("/api/courses/<int:course_id>/questions")
@require_login
@require_course_member
def api_questions_list(course_id):
    db = get_db()
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    items = QuestionsRepo(db).list_questions(course_id)
    if role not in STAFF_ROLES:
        for item in items:
            item.pop("answer_json", None)
    return jsonify({"questions": items})

@app.get("/courses/<int:course_id>/questions/new")
@require_login
@require_course_staff
def question_new_form(course_id):
    return render_template("question_new.html", course_id=course_id)

@app.post("/courses/<int:course_id>/questions")
@require_login
@require_course_staff
def question_create(course_id):
    db = get_db()
    qtype = (request.form.get("qtype") or "text").strip()
    prompt = (request.form.get("prompt") or "").strip()
    options_raw = (request.form.get("options_json") or "").strip() or None
    answer_raw = (request.form.get("answer_json") or "").strip() or None
    qid = QuestionsRepo(db).create_question(course_id, int(session["user_id"]), qtype, prompt, json_loads(options_raw) if options_raw else None, json_loads(answer_raw) if answer_raw else None)
    return redirect(url_for("question_detail", course_id=course_id, question_id=qid))

@app.post("/api/courses/<int:course_id>/questions")
@require_login
@require_course_staff
def api_question_create(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    qtype = (data.get("qtype") or "text").strip()
    prompt = (data.get("prompt") or "").strip()
    if not prompt:
        return jsonify({"error":"prompt_required"}), 400
    qid = QuestionsRepo(db).create_question(course_id, int(session["user_id"]), qtype, prompt, data.get("options_json"), data.get("answer_json"))
    return jsonify({"question_id": qid})

@app.get("/courses/<int:course_id>/questions/<int:question_id>")
@require_login
@require_course_member
def question_detail(course_id, question_id):
    db = get_db()
    q = QuestionsRepo(db).get_question(course_id, question_id)
    if not q:
        return "Not found", 404
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    if role not in STAFF_ROLES:
        q.pop("answer_json", None)
    return render_template("question_detail.html", course_id=course_id, q=q, my_role=role)

@app.get("/api/courses/<int:course_id>/questions/<int:question_id>")
@require_login
@require_course_member
def api_question_detail(course_id, question_id):
    db = get_db()
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    if role in STAFF_ROLES:
        q = QuestionsRepo(db).get_question_with_answer(course_id, question_id)
    else:
        q = QuestionsRepo(db).get_question(course_id, question_id)
    if not q:
        return jsonify({"error":"not_found"}), 404
    return jsonify({"question": q})

@app.get("/courses/<int:course_id>/questions/<int:question_id>/edit")
@require_login
@require_course_staff
def question_edit_form(course_id, question_id):
    db = get_db()
    q = QuestionsRepo(db).get_question(course_id, question_id)
    if not q:
        return "Not found", 404
    return render_template("question_edit.html", course_id=course_id, q=q)

@app.post("/courses/<int:course_id>/questions/<int:question_id>")
@require_login
@require_course_staff
def question_update(course_id, question_id):
    db = get_db()
    qtype = (request.form.get("qtype") or "text").strip()
    prompt = (request.form.get("prompt") or "").strip()
    options_raw = (request.form.get("options_json") or "").strip() or None
    answer_raw = (request.form.get("answer_json") or "").strip() or None
    QuestionsRepo(db).update_question(course_id, question_id, qtype, prompt, json_loads(options_raw) if options_raw else None, json_loads(answer_raw) if answer_raw else None)
    return redirect(url_for("question_detail", course_id=course_id, question_id=question_id))

@app.put("/api/courses/<int:course_id>/questions/<int:question_id>")
@require_login
@require_course_staff
def api_question_update(course_id, question_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    QuestionsRepo(db).update_question(course_id, question_id, (data.get("qtype") or "text").strip(), (data.get("prompt") or "").strip(), data.get("options_json"), data.get("answer_json"))
    return jsonify({"ok": True})

@app.post("/courses/<int:course_id>/questions/<int:question_id>/delete")
@require_login
@require_course_staff
def question_delete(course_id, question_id):
    db = get_db()
    QuestionsRepo(db).delete_question(course_id, question_id)
    return redirect(url_for("questions_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/questions/<int:question_id>")
@require_login
@require_course_staff
def api_question_delete(course_id, question_id):
    db = get_db()
    QuestionsRepo(db).delete_question(course_id, question_id)
    return jsonify({"ok": True})

# ========= L) Quiz =========
@app.get("/courses/<int:course_id>/quizzes")
@require_login
@require_course_member
def quizzes_list(course_id):
    db = get_db()
    items = QuizzesRepo(db).list_quizzes(course_id)
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    return render_template("quizzes_list.html", course_id=course_id, quizzes=items, my_role=role)

@app.get("/api/courses/<int:course_id>/quizzes")
@require_login
@require_course_member
def api_quizzes_list(course_id):
    db = get_db()
    return jsonify({"quizzes": QuizzesRepo(db).list_quizzes(course_id)})

@app.get("/courses/<int:course_id>/quizzes/new")
@require_login
@require_course_staff
def quiz_new_form(course_id):
    return render_template("quiz_new.html", course_id=course_id)

@app.post("/courses/<int:course_id>/quizzes")
@require_login
@require_course_staff
def quiz_create(course_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    open_at = (request.form.get("open_at") or "").strip() or None
    due_at = (request.form.get("due_at") or "").strip() or None
    qzid = QuizzesRepo(db).create_quiz(course_id, int(session["user_id"]), title, description, open_at, due_at)
    return redirect(url_for("quiz_detail", course_id=course_id, quiz_id=qzid))

@app.post("/api/courses/<int:course_id>/quizzes")
@require_login
@require_course_staff
def api_quiz_create(course_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    title = (data.get("title") or "").strip()
    description = (data.get("description") or "").strip()
    open_at = (data.get("open_at") or "").strip() or None
    due_at = (data.get("due_at") or "").strip() or None
    if not title:
        return jsonify({"error":"title_required"}), 400
    qzid = QuizzesRepo(db).create_quiz(course_id, int(session["user_id"]), title, description, open_at, due_at)
    return jsonify({"quiz_id": qzid})

@app.get("/courses/<int:course_id>/quizzes/<int:quiz_id>")
@require_login
@require_course_member
def quiz_detail(course_id, quiz_id):
    db = get_db()
    qz = QuizzesRepo(db).get_quiz(course_id, quiz_id)
    if not qz:
        return "Not found", 404
    role = MembershipsRepo(db).get_user_role(course_id, int(session["user_id"]))
    qq = QuizzesRepo(db).list_quiz_questions(quiz_id)
    return render_template("quiz_detail.html", course_id=course_id, quiz=qz, my_role=role, quiz_questions=qq)

@app.get("/api/courses/<int:course_id>/quizzes/<int:quiz_id>")
@require_login
@require_course_member
def api_quiz_detail(course_id, quiz_id):
    db = get_db()
    qz = QuizzesRepo(db).get_quiz(course_id, quiz_id)
    if not qz:
        return jsonify({"error":"not_found"}), 404
    return jsonify({"quiz": qz, "quiz_questions": QuizzesRepo(db).list_quiz_questions(quiz_id)})

@app.get("/courses/<int:course_id>/quizzes/<int:quiz_id>/edit")
@require_login
@require_course_staff
def quiz_edit_form(course_id, quiz_id):
    db = get_db()
    qz = QuizzesRepo(db).get_quiz(course_id, quiz_id)
    if not qz:
        return "Not found", 404
    return render_template("quiz_edit.html", course_id=course_id, quiz=qz)

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>")
@require_login
@require_course_staff
def quiz_update(course_id, quiz_id):
    db = get_db()
    title = (request.form.get("title") or "").strip()
    description = (request.form.get("description") or "").strip()
    open_at = (request.form.get("open_at") or "").strip() or None
    due_at = (request.form.get("due_at") or "").strip() or None
    QuizzesRepo(db).update_quiz(course_id, quiz_id, title, description, open_at, due_at)
    return redirect(url_for("quiz_detail", course_id=course_id, quiz_id=quiz_id))

@app.put("/api/courses/<int:course_id>/quizzes/<int:quiz_id>")
@require_login
@require_course_staff
def api_quiz_update(course_id, quiz_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    QuizzesRepo(db).update_quiz(course_id, quiz_id, (data.get("title") or "").strip(), (data.get("description") or "").strip(), (data.get("open_at") or "").strip() or None, (data.get("due_at") or "").strip() or None)
    return jsonify({"ok": True})

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/delete")
@require_login
@require_course_staff
def quiz_delete(course_id, quiz_id):
    db = get_db()
    QuizzesRepo(db).delete_quiz(course_id, quiz_id)
    return redirect(url_for("quizzes_list", course_id=course_id))

@app.delete("/api/courses/<int:course_id>/quizzes/<int:quiz_id>")
@require_login
@require_course_staff
def api_quiz_delete(course_id, quiz_id):
    db = get_db()
    QuizzesRepo(db).delete_quiz(course_id, quiz_id)
    return jsonify({"ok": True})

# FR-QZ6 configure questions
@app.get("/courses/<int:course_id>/quizzes/<int:quiz_id>/questions")
@require_login
@require_course_staff
def quiz_config_form(course_id, quiz_id):
    db = get_db()
    quiz = QuizzesRepo(db).get_quiz(course_id, quiz_id)
    questions = QuestionsRepo(db).list_questions(course_id)
    quiz_questions = QuizzesRepo(db).list_quiz_questions(quiz_id)
    return render_template("quiz_config.html", course_id=course_id, quiz=quiz, questions=questions, quiz_questions=quiz_questions)

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/questions")
@require_login
@require_course_staff
def quiz_add_question(course_id, quiz_id):
    db = get_db()
    question_id = int(request.form.get("question_id") or 0)
    points = int(request.form.get("points") or 1)
    position = int(request.form.get("position") or 1)
    QuizzesRepo(db).add_quiz_question(quiz_id, question_id, points, position)
    return redirect(url_for("quiz_config_form", course_id=course_id, quiz_id=quiz_id))

@app.post("/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions")
@require_login
@require_course_staff
def api_quiz_add_question(course_id, quiz_id):
    db = get_db()
    data = request.get_json(force=True, silent=True) or {}
    QuizzesRepo(db).add_quiz_question(quiz_id, int(data.get("question_id") or 0), int(data.get("points") or 1), int(data.get("position") or 1))
    return jsonify({"ok": True})

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/questions/<int:question_id>/delete")
@require_login
@require_course_staff
def quiz_remove_question(course_id, quiz_id, question_id):
    db = get_db()
    QuizzesRepo(db).remove_quiz_question(quiz_id, question_id)
    return redirect(url_for("quiz_config_form", course_id=course_id, quiz_id=quiz_id))

@app.delete("/api/courses/<int:course_id>/quizzes/<int:quiz_id>/questions/<int:question_id>")
@require_login
@require_course_staff
def api_quiz_remove_question(course_id, quiz_id, question_id):
    db = get_db()
    QuizzesRepo(db).remove_quiz_question(quiz_id, question_id)
    return jsonify({"ok": True})

# ========= M) Student take quiz =========
@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/start")
@require_login
@require_student
def start_attempt(course_id, quiz_id):
    db = get_db()
    attempt_id = QuizzesRepo(db).start_attempt(quiz_id, course_id, int(session["user_id"]))
    return redirect(url_for("take_quiz", course_id=course_id, quiz_id=quiz_id, attempt_id=attempt_id))

@app.post("/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/start")
@require_login
@require_student
def api_start_attempt(course_id, quiz_id):
    db = get_db()
    attempt_id = QuizzesRepo(db).start_attempt(quiz_id, course_id, int(session["user_id"]))
    return jsonify({"attempt_id": attempt_id})

@app.get("/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>")
@require_login
@require_student
def take_quiz(course_id, quiz_id, attempt_id):
    db = get_db()
    attempt = QuizzesRepo(db).get_attempt(attempt_id)
    if not attempt or attempt["student_id"] != int(session["user_id"]):
        return "Not found", 404
    quiz = QuizzesRepo(db).get_quiz(course_id, quiz_id)
    qq = QuizzesRepo(db).list_quiz_questions(quiz_id)
    qs = []
    for item in qq:
        q = QuestionsRepo(db).get_question(course_id, item["question_id"])
        if q:
            q["options_obj"] = json_loads(q.get("options_json"))
            qs.append({"qq": item, "q": q})
    return render_template("quiz_take.html", course_id=course_id, quiz=quiz, attempt=attempt, questions=qs)

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers")
@require_login
@require_student
def answer_question(course_id, quiz_id, attempt_id):
    db = get_db()
    attempt = QuizzesRepo(db).get_attempt(attempt_id)
    if not attempt or attempt["student_id"] != int(session["user_id"]):
        return "Not found", 404
    question_id = int(request.form.get("question_id") or 0)
    answer_raw = (request.form.get("answer_json") or "").strip() or "{}"
    QuizzesRepo(db).upsert_answer(attempt_id, question_id, answer_raw)
    return redirect(url_for("take_quiz", course_id=course_id, quiz_id=quiz_id, attempt_id=attempt_id))

@app.post("/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/answers")
@require_login
@require_student
def api_answer_question(course_id, quiz_id, attempt_id):
    db = get_db()
    attempt = QuizzesRepo(db).get_attempt(attempt_id)
    if not attempt or attempt["student_id"] != int(session["user_id"]):
        return jsonify({"error":"not_found"}), 404
    data = request.get_json(force=True, silent=True) or {}
    QuizzesRepo(db).upsert_answer(attempt_id, int(data.get("question_id") or 0), json_dumps(data.get("answer_json")) or "{}")
    return jsonify({"ok": True})

@app.post("/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit")
@require_login
@require_student
def submit_attempt(course_id, quiz_id, attempt_id):
    db = get_db()
    attempt = QuizzesRepo(db).get_attempt(attempt_id)
    if not attempt or attempt["student_id"] != int(session["user_id"]):
        return "Not found", 404
    QuizzesRepo(db).submit_attempt(attempt_id)
    return redirect(url_for("quizzes_list", course_id=course_id))

@app.post("/api/courses/<int:course_id>/quizzes/<int:quiz_id>/attempts/<int:attempt_id>/submit")
@require_login
@require_student
def api_submit_attempt(course_id, quiz_id, attempt_id):
    db = get_db()
    attempt = QuizzesRepo(db).get_attempt(attempt_id)
    if not attempt or attempt["student_id"] != int(session["user_id"]):
        return jsonify({"error":"not_found"}), 404
    QuizzesRepo(db).submit_attempt(attempt_id)
    return jsonify({"ok": True})

@app.get("/my/quizzes")
@require_login
def my_quiz_attempts_page():
    db = get_db()
    attempts = QuizzesRepo(db).list_my_attempts(int(session["user_id"]))
    return render_template("my_quiz_attempts.html", attempts=attempts)

@app.get("/api/my/quizzes/attempts")
@require_login
def api_my_quiz_attempts():
    db = get_db()
    attempts = QuizzesRepo(db).list_my_attempts(int(session["user_id"]))
    return jsonify({"attempts": attempts})

if __name__ == "__main__":
    app.run(debug=True)