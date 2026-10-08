"""Server-served pages for every actor-facing workflow."""
from flask import (
    Blueprint,
    abort,
    flash,
    redirect,
    render_template,
    request,
    url_for,
)

from . import models
from .auth import (
    clear_session_cookie,
    current_user,
    get_repo,
    login_required,
    role_required,
    set_session_cookie,
)
from .services import (
    AccountService,
    AdminService,
    AuditService,
    CalculatedService,
    ChartService,
    DataSourceService,
    DatasetService,
    ExportService,
    FilterService,
    SharingService,
)

pages_bp = Blueprint("pages", __name__)


def _config():
    from flask import current_app

    return current_app.config


# ---------------------------------------------------------------------------
# Authentication pages (DATA-01)
# ---------------------------------------------------------------------------

@pages_bp.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        username = request.form.get("username", "")
        password = request.form.get("password", "")
        repo = get_repo()
        account = AccountService(repo, _config())
        try:
            user, token = account.signin(username, password)
            repo.commit()
        except ValueError as exc:
            flash(str(exc), "error")
            return redirect(url_for("pages.login"))
        response = redirect(request.args.get("next") or _home_for(user.role))
        set_session_cookie(response, token)
        return response
    return render_template("login.html")


@pages_bp.route("/signup", methods=["GET", "POST"])
def signup():
    if request.method == "POST":
        repo = get_repo()
        account = AccountService(repo, _config())
        try:
            user = account.signup(
                request.form.get("username", ""),
                request.form.get("email", ""),
                request.form.get("password", ""),
            )
            repo.commit()
        except ValueError as exc:
            flash(str(exc), "error")
            return redirect(url_for("pages.signup"))
        repo2 = get_repo()
        try:
            user, token = AccountService(repo2, _config()).signin(
                user.username, request.form.get("password", "")
            )
            repo2.commit()
        except ValueError:
            return redirect(url_for("pages.login"))
        response = redirect(_home_for(user.role))
        set_session_cookie(response, token)
        return response
    return render_template("signup.html")


@pages_bp.route("/logout", methods=["POST"])
@login_required
def logout():
    user = current_user()
    token = request.cookies.get("session_token")
    repo = get_repo()
    AccountService(repo, _config()).signout(user, token)
    repo.commit()
    response = redirect(url_for("pages.login"))
    return clear_session_cookie(response)


def _home_for(role):
    return url_for("pages.index")


# ---------------------------------------------------------------------------
# Index / dashboard
# ---------------------------------------------------------------------------

@pages_bp.route("/")
@login_required
def index():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    context = {"user": user, "role": user.role}
    if user.role == models.ROLE_ADMIN:
        context["users_count"] = repo.count(models.User)
        context["datasets_count"] = repo.count(models.Dataset)
        context["shares_count"] = repo.count(models.DashboardSharing)
        context["settings"] = {
            "retention_days": repo.setting("retention_days", "30"),
            "max_datasets_per_analyst": repo.setting("max_datasets_per_analyst", "50"),
            "max_upload_mb": repo.setting("max_upload_mb", "10"),
        }
        context["recent_events"] = repo.audit_events(limit=8)
    elif user.role == models.ROLE_ANALYST:
        context["counts"] = repo.analytics_counts(user.id)
        context["recent_lineage"] = repo.lineage_for(user_id=user.id, limit=8)
        context["uploads"] = repo.list_all(models.DatasetUpload, owner_id=user.id)[:5]
    else:
        context["catalog_count"] = len(dataset_service.visible_catalog(user))
        context["catalog"] = dataset_service.visible_catalog(user)[:8]
    return render_template("index.html", **context)


# ---------------------------------------------------------------------------
# Account access page (DATA-01)
# ---------------------------------------------------------------------------

@pages_bp.route("/account")
@login_required
def account():
    user = current_user()
    repo = get_repo()
    account = AccountService(repo, _config())
    sessions = repo.sessions_for_user(user.id)
    log = account.access_log(user)
    return render_template(
        "account.html", user=user, sessions=sessions, access_log=log
    )


# ---------------------------------------------------------------------------
# Dataset catalog (DATA-03) and upload (DATA-02)
# ---------------------------------------------------------------------------

@pages_bp.route("/datasets")
@login_required
def datasets():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    search = request.args.get("q", "")
    tag = request.args.get("tag", "")
    entries = dataset_service.visible_catalog(user, search=search, tag=tag)
    rows = []
    for entry in entries:
        dataset = repo.get(models.Dataset, entry.dataset_id)
        owner = repo.get(models.User, entry.owner_id)
        rows.append(
            {
                "id": entry.id,
                "dataset_id": entry.dataset_id,
                "name": entry.name,
                "description": entry.description,
                "tags": [t.strip() for t in entry.tags.split(",") if t.strip()],
                "visibility": entry.visibility,
                "owner": owner.username if owner else "?",
                "row_count": dataset.row_count if dataset else 0,
                "column_count": dataset.column_count if dataset else 0,
                "created_at": entry.created_at,
            }
        )
    owned = repo.list_all(models.Dataset, owner_id=user.id)
    return render_template(
        "datasets.html",
        user=user,
        rows=rows,
        search=search,
        tag=tag,
        owned=owned,
    )


@pages_bp.route("/datasets/upload", methods=["GET", "POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def dataset_upload():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    if request.method == "POST":
        try:
            record = dataset_service.upload(
                user,
                request.files.get("file"),
                request.form.get("name", ""),
                request.form.get("description", ""),
                request.form.get("tags", ""),
                request.form.get("visibility", "private"),
            )
            repo.commit()
            flash(f"Dataset '{record.filename}' uploaded with schema detection.", "success")
        except ValueError as exc:
            repo.rollback()
            flash(str(exc), "error")
        return redirect(url_for("pages.datasets"))
    uploads = repo.list_all(models.DatasetUpload, owner_id=user.id)
    return render_template("dataset_upload.html", user=user, uploads=uploads)


# ---------------------------------------------------------------------------
# Data preview (DATA-04)
# ---------------------------------------------------------------------------

@pages_bp.route("/datasets/<int:dataset_id>/preview")
@login_required
def dataset_preview(dataset_id):
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    try:
        data = dataset_service.dataset_rows(user, dataset_id)
        record = dataset_service.preview(user, dataset_id)
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        flash(str(exc), "error")
        return redirect(url_for("pages.datasets"))
    summary = record.summary_json
    preview_rows = data["rows"][:50]
    return render_template(
        "preview.html",
        user=user,
        dataset=data,
        summary=summary,
        preview_rows=preview_rows,
    )


# ---------------------------------------------------------------------------
# Filter builder (DATA-05)
# ---------------------------------------------------------------------------

@pages_bp.route("/filters")
@login_required
def filters():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    datasets = dataset_service.accessible_datasets(user)
    records = repo.list_all(models.FilterBuilder, owner_id=user.id)
    for record in records:
        record.dataset_name = _dataset_name(repo, record.dataset_id)
    return render_template(
        "filters.html", user=user, datasets=datasets, records=records
    )


# ---------------------------------------------------------------------------
# Chart builder (DATA-06)
# ---------------------------------------------------------------------------

@pages_bp.route("/charts")
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def charts():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    datasets = dataset_service.accessible_datasets(user)
    records = repo.list_all(models.ChartBuilder, owner_id=user.id)
    for record in records:
        record.dataset_name = _dataset_name(repo, record.dataset_id)
    return render_template(
        "charts.html", user=user, datasets=datasets, records=records
    )


# ---------------------------------------------------------------------------
# Calculated columns (DATA-07)
# ---------------------------------------------------------------------------

@pages_bp.route("/calculated")
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def calculated():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    datasets = dataset_service.accessible_datasets(user)
    records = repo.list_all(models.CalculatedColumns, owner_id=user.id)
    for record in records:
        record.dataset_name = _dataset_name(repo, record.dataset_id)
    return render_template(
        "calculated.html", user=user, datasets=datasets, records=records
    )


# ---------------------------------------------------------------------------
# Dashboard sharing (DATA-08)
# ---------------------------------------------------------------------------

@pages_bp.route("/sharing")
@login_required
def sharing():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    datasets = dataset_service.accessible_datasets(user)
    charts = repo.list_all(models.ChartBuilder, owner_id=user.id)
    records = repo.list_all(models.DashboardSharing, owner_id=user.id)
    for record in records:
        record.dataset_name = (
            _dataset_name(repo, record.dataset_id) if record.dataset_id else ""
        )
        record.chart_name = (
            repo.get(models.ChartBuilder, record.chart_id).name
            if record.chart_id
            else ""
        )
    return render_template(
        "sharing.html",
        user=user,
        datasets=datasets,
        charts=charts,
        records=records,
    )


@pages_bp.route("/shared/<token>")
def shared(token):
    """Public dashboard view accessed through a share link (no sign-in)."""
    repo = get_repo()
    share = repo.session.query(models.DashboardSharing).filter_by(link_token=token).first()
    if not share:
        abort(404)
    context = {"share": share, "owner": None, "dataset": None, "chart": None}
    if share.owner_id:
        context["owner"] = repo.get(models.User, share.owner_id)
    if share.dataset_id:
        dataset = repo.get(models.Dataset, share.dataset_id)
        if dataset:
            context["dataset"] = {
                "name": dataset.name,
                "columns": dataset.data_json["columns"],
                "rows": dataset.data_json["rows"][:50],
                "row_count": dataset.row_count,
            }
    if share.chart_id:
        chart = repo.get(models.ChartBuilder, share.chart_id)
        if chart:
            context["chart"] = chart
    return render_template("shared.html", **context)


# ---------------------------------------------------------------------------
# Export (DATA-09)
# ---------------------------------------------------------------------------

@pages_bp.route("/exports")
@login_required
def exports():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    datasets = dataset_service.accessible_datasets(user)
    records = repo.list_all(models.Export, owner_id=user.id)
    for record in records:
        record.dataset_name = _dataset_name(repo, record.dataset_id)
    return render_template(
        "exports.html", user=user, datasets=datasets, records=records
    )


# ---------------------------------------------------------------------------
# Data source connections (DATA-10)
# ---------------------------------------------------------------------------

@pages_bp.route("/sources")
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def sources():
    user = current_user()
    repo = get_repo()
    dataset_service = DatasetService(repo, _config())
    source_service = DataSourceService(repo, dataset_service)
    records = source_service.list(user, admin=user.role == models.ROLE_ADMIN)
    for record in records:
        owner = repo.get(models.User, record.owner_id)
        record.owner_name = owner.username if owner else "?"
    return render_template(
        "sources.html", user=user, records=records
    )


# ---------------------------------------------------------------------------
# Audit and lineage (DATA-11)
# ---------------------------------------------------------------------------

@pages_bp.route("/audit")
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def audit():
    user = current_user()
    repo = get_repo()
    audit_service = AuditService(repo)
    admin = user.role == models.ROLE_ADMIN
    lineage = audit_service.lineage(user, admin=admin)
    events = audit_service.events(user, admin=admin)
    for item in lineage:
        actor = repo.get(models.User, item.user_id)
        item.actor = actor.username if actor else "?"
    for item in events:
        actor = repo.get(models.User, item.user_id)
        item.actor = actor.username if actor else "?"
    return render_template(
        "audit.html", user=user, lineage=lineage, events=events
    )


# ---------------------------------------------------------------------------
# Admin operations (DATA-12)
# ---------------------------------------------------------------------------

@pages_bp.route("/admin")
@login_required
@role_required(models.ROLE_ADMIN)
def admin_page():
    user = current_user()
    repo = get_repo()
    admin_service = AdminService(repo)
    users = admin_service.users()
    ops = admin_service.operations()
    settings = {
        "retention_days": repo.setting("retention_days", "30"),
        "max_datasets_per_analyst": repo.setting("max_datasets_per_analyst", "50"),
        "max_upload_mb": repo.setting("max_upload_mb", "10"),
    }
    return render_template(
        "admin.html", user=user, users=users, ops=ops, settings=settings
    )


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _dataset_name(repo, dataset_id):
    dataset = repo.get(models.Dataset, dataset_id)
    return dataset.name if dataset else "?"
