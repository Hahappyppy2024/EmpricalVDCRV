"""HTTP/JSON API for the P12 Data Analytics Dashboard.

Implements the use-case contract:
    GET   /api/data/<slug>
    POST  /api/data/<slug>
    PATCH /api/data/<slug>/<id>
for every slug in {account_access, dataset_upload, dataset_catalog,
data_preview, filter_builder, chart_builder, calculated_columns,
dashboard_sharing, export, data_source_connections, audit_and_lineage,
admin_operations}.
"""
import json

from flask import Blueprint, jsonify, request, send_file

from . import analytics, models
from .auth import (
    clear_session_cookie,
    current_user,
    get_repo,
    login_required,
    role_required,
    set_session_cookie,
)
from .config import Config
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

api_bp = Blueprint("api", __name__)


def _dataset_service():
    return DatasetService(get_repo(), Config())


def ok(data=None, status=200):
    return jsonify({"ok": True, **(data or {})}), status


def fail(error, status=400):
    return jsonify({"ok": False, "error": error}), status


def _home_for(role):
    return "/"


# ---------------------------------------------------------------------------
# DATA-01  account_access
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/account_access", methods=["GET"])
@login_required
def get_account_access():
    user = current_user()
    repo = get_repo()
    service = AccountService(repo, Config())
    records = service.access_log(user)
    return ok({"slug": "account_access", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/account_access", methods=["POST"])
def post_account_access():
    data = request.form
    action = data.get("action", "")
    repo = get_repo()
    service = AccountService(repo, Config())
    if action == "signin":
        try:
            user, token = service.signin(data.get("username", ""), data.get("password", ""))
        except ValueError as exc:
            repo.rollback()
            return fail(str(exc), 401)
        repo.commit()
        response, status = ok(
            {
                "slug": "account_access",
                "action": "signin",
                "outcome": "success",
                "user": models.serialize(user),
                "redirect": _home_for(user.role),
            }
        )
        set_session_cookie(response, token)
        return response, status
    if action == "signup":
        try:
            user = service.signup(
                data.get("username", ""), data.get("email", ""), data.get("password", "")
            )
        except ValueError as exc:
            repo.rollback()
            return fail(str(exc))
        repo.commit()
        try:
            user, token = AccountService(get_repo(), Config()).signin(
                user.username, data.get("password", "")
            )
            get_repo().commit()
        except ValueError:
            return ok({"slug": "account_access", "action": "signup", "outcome": "success"})
        response, status = ok(
            {
                "slug": "account_access",
                "action": "signup",
                "outcome": "success",
                "user": models.serialize(user),
                "redirect": _home_for(user.role),
            }
        )
        set_session_cookie(response, token)
        return response, status
    if action == "signout":
        user = current_user()
        token = request.cookies.get("session_token")
        if user:
            service.signout(user, token)
            repo.commit()
        response, status = ok(
            {"slug": "account_access", "action": "signout", "outcome": "success"}
        )
        clear_session_cookie(response)
        return response, status
    if action == "revoke":
        user = current_user()
        if user is None:
            return fail("Authentication required", 401)
        try:
            service.revoke_session(user, data.get("token", ""))
        except ValueError as exc:
            repo.rollback()
            return fail(str(exc))
        repo.commit()
        return ok({"slug": "account_access", "action": "revoke", "outcome": "success"})
    return fail("Unknown action: " + action)


@api_bp.route("/api/data/account_access/<int:item_id>", methods=["PATCH"])
@login_required
def patch_account_access(item_id):
    repo = get_repo()
    record = repo.get(models.AccountAccess, item_id)
    if not record or record.user_id != current_user().id:
        return fail("Unknown or out-of-scope record", 404)
    return ok({"slug": "account_access", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-02  dataset_upload
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/dataset_upload", methods=["GET"])
@login_required
def get_dataset_upload():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.DatasetUpload, owner_id=user.id)
    return ok({"slug": "dataset_upload", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/dataset_upload", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_dataset_upload():
    user = current_user()
    repo = get_repo()
    service = _dataset_service()
    try:
        record = service.upload(
            user,
            request.files.get("file"),
            request.form.get("name", ""),
            request.form.get("description", ""),
            request.form.get("tags", ""),
            request.form.get("visibility", "private"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    dataset = repo.get(models.Dataset, record.dataset_id)
    return ok(
        {
            "slug": "dataset_upload",
            "record": models.serialize(record),
            "dataset": {
                "id": dataset.id,
                "name": dataset.name,
                "rows": dataset.row_count,
                "columns": dataset.column_count,
            },
        }
    )


@api_bp.route("/api/data/dataset_upload/<int:item_id>", methods=["PATCH"])
@login_required
def patch_dataset_upload(item_id):
    repo = get_repo()
    record = repo.get(models.DatasetUpload, item_id)
    if not record or record.owner_id != current_user().id:
        return fail("Unknown or out-of-scope record", 404)
    return ok({"slug": "dataset_upload", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-03  dataset_catalog
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/dataset_catalog", methods=["GET"])
@login_required
def get_dataset_catalog():
    user = current_user()
    service = _dataset_service()
    entries = service.visible_catalog(
        user,
        search=request.args.get("q", ""),
        tag=request.args.get("tag", ""),
    )
    repo = get_repo()
    records = []
    for entry in entries:
        item = models.serialize(entry)
        dataset = repo.get(models.Dataset, entry.dataset_id)
        owner = repo.get(models.User, entry.owner_id)
        item["dataset_row_count"] = dataset.row_count if dataset else 0
        item["owner_username"] = owner.username if owner else "?"
        records.append(item)
    return ok({"slug": "dataset_catalog", "records": records})


@api_bp.route("/api/data/dataset_catalog", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_dataset_catalog():
    user = current_user()
    repo = get_repo()
    service = _dataset_service()
    try:
        entry = service.create_catalog_entry(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("name", ""),
            request.form.get("description", ""),
            request.form.get("tags", ""),
            request.form.get("visibility", "private"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok({"slug": "dataset_catalog", "record": models.serialize(entry)})


@api_bp.route("/api/data/dataset_catalog/<int:item_id>", methods=["PATCH"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def patch_dataset_catalog(item_id):
    user = current_user()
    repo = get_repo()
    service = _dataset_service()
    try:
        entry = service.update_catalog_entry(
            user, item_id, **{k: request.form.get(k) for k in (
                "name", "description", "tags", "visibility",
            )}
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok({"slug": "dataset_catalog", "record": models.serialize(entry)})


# ---------------------------------------------------------------------------
# DATA-04  data_preview
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/data_preview", methods=["GET"])
@login_required
def get_data_preview():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.DataPreview, user_id=user.id)[:50]
    return ok({"slug": "data_preview", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/data_preview", methods=["POST"])
@login_required
def post_data_preview():
    user = current_user()
    repo = get_repo()
    service = _dataset_service()
    try:
        record = service.preview(user, request.form.get("dataset_id", type=int))
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok(
        {
            "slug": "data_preview",
            "record": models.serialize(record),
            "summary": record.summary_json,
            "preview": record.preview_json,
        }
    )


@api_bp.route("/api/data/data_preview/<int:item_id>", methods=["PATCH"])
@login_required
def patch_data_preview(item_id):
    repo = get_repo()
    record = repo.get(models.DataPreview, item_id)
    if not record or record.user_id != current_user().id:
        return fail("Unknown or out-of-scope record", 404)
    return ok({"slug": "data_preview", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-05  filter_builder
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/filter_builder", methods=["GET"])
@login_required
def get_filter_builder():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.FilterBuilder, owner_id=user.id)
    return ok({"slug": "filter_builder", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/filter_builder", methods=["POST"])
@login_required
def post_filter_builder():
    user = current_user()
    repo = get_repo()
    service = FilterService(repo, _dataset_service())
    try:
        record = service.create(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("name", ""),
            request.form.get("expression", ""),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok(
        {
            "slug": "filter_builder",
            "record": models.serialize(record),
            "matched_rows": record.row_count,
        }
    )


@api_bp.route("/api/data/filter_builder/<int:item_id>", methods=["PATCH"])
@login_required
def patch_filter_builder(item_id):
    user = current_user()
    repo = get_repo()
    service = FilterService(repo, _dataset_service())
    try:
        record = service.update(
            user, item_id,
            name=request.form.get("name"),
            expression=request.form.get("expression"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok({"slug": "filter_builder", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-06  chart_builder
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/chart_builder", methods=["GET"])
@login_required
def get_chart_builder():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.ChartBuilder, owner_id=user.id)
    return ok({"slug": "chart_builder", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/chart_builder", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_chart_builder():
    user = current_user()
    repo = get_repo()
    service = ChartService(repo, _dataset_service())
    try:
        record = service.create(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("name", ""),
            request.form.get("chart_type", "bar"),
            request.form.get("x_col", ""),
            request.form.get("y_col", ""),
            request.form.get("agg", "sum"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok(
        {
            "slug": "chart_builder",
            "record": models.serialize(record),
            "series": record.config_json.get("series"),
        }
    )


@api_bp.route("/api/data/chart_builder/<int:item_id>", methods=["PATCH"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def patch_chart_builder(item_id):
    user = current_user()
    repo = get_repo()
    service = ChartService(repo, _dataset_service())
    try:
        record = service.update(
            user, item_id,
            name=request.form.get("name"),
            x_col=request.form.get("x_col"),
            y_col=request.form.get("y_col"),
            agg=request.form.get("agg"),
            chart_type=request.form.get("chart_type"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok({"slug": "chart_builder", "record": models.serialize(record)})


@api_bp.route("/api/data/chart_builder/<int:item_id>/data", methods=["GET"])
@login_required
def get_chart_data(item_id):
    user = current_user()
    repo = get_repo()
    record = repo.get(models.ChartBuilder, item_id)
    if not record or record.owner_id != user.id:
        return fail("Unknown or out-of-scope chart", 404)
    return ok(
        {"slug": "chart_builder", "id": record.id, "series": record.config_json.get("series")}
    )


# ---------------------------------------------------------------------------
# DATA-07  calculated_columns
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/calculated_columns", methods=["GET"])
@login_required
def get_calculated_columns():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.CalculatedColumns, owner_id=user.id)
    return ok({"slug": "calculated_columns", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/calculated_columns", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_calculated_columns():
    user = current_user()
    repo = get_repo()
    service = CalculatedService(repo, _dataset_service())
    try:
        record = service.create(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("name", ""),
            request.form.get("column_name", ""),
            request.form.get("expression", ""),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok({"slug": "calculated_columns", "record": models.serialize(record)})


@api_bp.route("/api/data/calculated_columns/<int:item_id>", methods=["PATCH"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def patch_calculated_columns(item_id):
    user = current_user()
    repo = get_repo()
    service = CalculatedService(repo, _dataset_service())
    try:
        record = service.update(
            user, item_id,
            name=request.form.get("name"),
            column_name=request.form.get("column_name"),
            expression=request.form.get("expression"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok({"slug": "calculated_columns", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-08  dashboard_sharing
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/dashboard_sharing", methods=["GET"])
@login_required
def get_dashboard_sharing():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.DashboardSharing, owner_id=user.id)
    return ok({"slug": "dashboard_sharing", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/dashboard_sharing", methods=["POST"])
@login_required
def post_dashboard_sharing():
    user = current_user()
    repo = get_repo()
    service = SharingService(repo, _dataset_service())
    try:
        record = service.create(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("chart_id", type=int),
            request.form.get("share_type", "link"),
            request.form.get("target", ""),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    share_url = f"/shared/{record.link_token}" if record.link_token else ""
    return ok(
        {
            "slug": "dashboard_sharing",
            "record": models.serialize(record),
            "share_url": share_url,
        }
    )


@api_bp.route("/api/data/dashboard_sharing/<int:item_id>", methods=["PATCH"])
@login_required
def patch_dashboard_sharing(item_id):
    user = current_user()
    repo = get_repo()
    service = SharingService(repo, _dataset_service())
    try:
        record = service.update(
            user, item_id,
            target=request.form.get("target"),
            share_type=request.form.get("share_type"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc), 404)
    return ok({"slug": "dashboard_sharing", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-09  export
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/export", methods=["GET"])
@login_required
def get_export():
    user = current_user()
    repo = get_repo()
    records = repo.list_all(models.Export, owner_id=user.id)
    return ok({"slug": "export", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/export", methods=["POST"])
@login_required
def post_export():
    user = current_user()
    repo = get_repo()
    service = ExportService(repo, _dataset_service(), Config())
    try:
        record = service.create(
            user,
            request.form.get("dataset_id", type=int),
            request.form.get("kind", "csv"),
            request.form.get("filter_ref", ""),
            request.form.get("name", ""),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok(
        {
            "slug": "export",
            "record": models.serialize(record),
            "download_url": f"/api/data/export/download/{record.id}",
        }
    )


@api_bp.route("/api/data/export/<int:item_id>", methods=["PATCH"])
@login_required
def patch_export(item_id):
    repo = get_repo()
    record = repo.get(models.Export, item_id)
    if not record or record.owner_id != current_user().id:
        return fail("Unknown or out-of-scope record", 404)
    return ok({"slug": "export", "record": models.serialize(record)})


@api_bp.route("/api/data/export/download/<int:export_id>")
@login_required
def export_download(export_id):
    user = current_user()
    repo = get_repo()
    service = ExportService(repo, _dataset_service(), Config())
    try:
        record = service.get(user, export_id)
    except ValueError as exc:
        return fail(str(exc), 404)
    stored = repo.get(models.StoredFile, record.file_id)
    if not stored:
        return fail("Export file is missing", 404)
    return send_file(
        stored.storage_path,
        as_attachment=True,
        download_name=stored.filename,
    )


# ---------------------------------------------------------------------------
# DATA-10  data_source_connections
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/data_source_connections", methods=["GET"])
@login_required
def get_data_source_connections():
    user = current_user()
    repo = get_repo()
    filters = {} if user.role == models.ROLE_ADMIN else {"owner_id": user.id}
    records = repo.list_all(models.DataSourceConnections, **filters)
    return ok({"slug": "data_source_connections", "records": [models.serialize(r) for r in records]})


@api_bp.route("/api/data/data_source_connections", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_data_source_connections():
    user = current_user()
    repo = get_repo()
    service = DataSourceService(repo, _dataset_service())
    data = request.form
    action = data.get("action", "create")
    if action == "test":
        try:
            record, rows = service.test(user, data.get("id", type=int))
            repo.commit()
        except ValueError as exc:
            repo.rollback()
            return fail(str(exc), 404)
        return ok(
            {
                "slug": "data_source_connections",
                "action": "test",
                "record": models.serialize(record),
                "sample_columns": rows["columns"],
                "sample_rows": rows["rows"][:5],
            }
        )
    if action == "import":
        try:
            dataset = service.import_dataset(
                user, data.get("id", type=int), data.get("dataset_name", "")
            )
            repo.commit()
        except ValueError as exc:
            repo.rollback()
            return fail(str(exc))
        return ok(
            {
                "slug": "data_source_connections",
                "action": "import",
                "dataset": models.serialize(dataset),
            }
        )
    config_text = data.get("config", "")
    config = {}
    if config_text.strip():
        try:
            config = json.loads(config_text)
        except ValueError:
            return fail("config must be valid JSON")
    try:
        record = service.create(
            user, data.get("name", ""), data.get("kind", ""), config
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok({"slug": "data_source_connections", "record": models.serialize(record)})


@api_bp.route("/api/data/data_source_connections/<int:item_id>", methods=["PATCH"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def patch_data_source_connections(item_id):
    user = current_user()
    repo = get_repo()
    record = repo.get(models.DataSourceConnections, item_id)
    if not record or record.owner_id != user.id:
        return fail("Unknown or out-of-scope record", 404)
    if request.form.get("config") is not None:
        try:
            record.config_json = json.loads(request.form.get("config"))
        except ValueError:
            return fail("config must be valid JSON")
    if request.form.get("status") is not None:
        record.status = request.form.get("status")
    repo.commit()
    return ok({"slug": "data_source_connections", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-11  audit_and_lineage
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/audit_and_lineage", methods=["GET"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def get_audit_and_lineage():
    user = current_user()
    repo = get_repo()
    service = AuditService(repo)
    admin = user.role == models.ROLE_ADMIN
    lineage = service.lineage(user, admin=admin)
    events = service.events(user, admin=admin)
    return ok(
        {
            "slug": "audit_and_lineage",
            "lineage": [models.serialize(r) for r in lineage],
            "events": [models.serialize(r) for r in events],
        }
    )


@api_bp.route("/api/data/audit_and_lineage", methods=["POST"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def post_audit_and_lineage():
    user = current_user()
    repo = get_repo()
    operation = request.form.get("operation", "")
    if operation not in ("import", "filter", "chart", "calculated", "export", "share", "source"):
        return fail("Invalid operation; expected import/filter/chart/calculated/export/share/source")
    record = repo.add(
        models.AuditAndLineage(
            user_id=user.id,
            dataset_id=request.form.get("dataset_id", type=int),
            operation=operation,
            source_ref=request.form.get("source_ref", ""),
            output_ref=request.form.get("output_ref", ""),
            detail=request.form.get("detail", ""),
        )
    )
    repo.commit()
    return ok({"slug": "audit_and_lineage", "record": models.serialize(record)})


@api_bp.route("/api/data/audit_and_lineage/<int:item_id>", methods=["PATCH"])
@login_required
def patch_audit_and_lineage(item_id):
    user = current_user()
    repo = get_repo()
    record = repo.get(models.AuditAndLineage, item_id)
    if not record or record.user_id != user.id:
        return fail("Unknown or out-of-scope record", 404)
    if request.form.get("detail") is not None:
        record.detail = request.form.get("detail")
    repo.commit()
    return ok({"slug": "audit_and_lineage", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# DATA-12  admin_operations
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/admin_operations", methods=["GET"])
@login_required
@role_required(models.ROLE_ADMIN)
def get_admin_operations():
    user = current_user()
    repo = get_repo()
    service = AdminService(repo)
    ops = service.operations()
    users = service.users()
    settings = {
        "retention_days": repo.setting("retention_days", "30"),
        "max_datasets_per_analyst": repo.setting("max_datasets_per_analyst", "50"),
        "max_upload_mb": repo.setting("max_upload_mb", "10"),
    }
    return ok(
        {
            "slug": "admin_operations",
            "operations": [models.serialize(r) for r in ops],
            "users": [models.serialize(u) for u in users],
            "settings": settings,
        }
    )


@api_bp.route("/api/data/admin_operations", methods=["POST"])
@login_required
@role_required(models.ROLE_ADMIN)
def post_admin_operations():
    user = current_user()
    repo = get_repo()
    service = AdminService(repo)
    try:
        record = service.perform(
            user,
            request.form.get("action", ""),
            request.form.get("target", ""),
            request.form.get("detail", ""),
            request.form.get("value"),
        )
        repo.commit()
    except ValueError as exc:
        repo.rollback()
        return fail(str(exc))
    return ok({"slug": "admin_operations", "record": models.serialize(record)})


@api_bp.route("/api/data/admin_operations/<int:item_id>", methods=["PATCH"])
@login_required
@role_required(models.ROLE_ADMIN)
def patch_admin_operations(item_id):
    repo = get_repo()
    record = repo.get(models.AdminOperations, item_id)
    if not record:
        return fail("Unknown record", 404)
    if request.form.get("detail") is not None:
        record.detail = request.form.get("detail")
    repo.commit()
    return ok({"slug": "admin_operations", "record": models.serialize(record)})


# ---------------------------------------------------------------------------
# Data access endpoints used by the browser client
# ---------------------------------------------------------------------------

@api_bp.route("/api/data/dataset_rows")
@login_required
def dataset_rows():
    user = current_user()
    dataset_id = request.args.get("dataset_id", type=int)
    if not dataset_id:
        return fail("dataset_id is required")
    service = _dataset_service()
    try:
        data = service.dataset_rows(user, dataset_id)
    except ValueError as exc:
        return fail(str(exc), 404)
    return ok({"slug": "dataset_rows", **data})


@api_bp.route("/api/data/filter_builder/preview", methods=["GET"])
@login_required
def filter_preview():
    user = current_user()
    dataset_id = request.args.get("dataset_id", type=int)
    expression = request.args.get("expression", "")
    if not dataset_id:
        return fail("dataset_id is required")
    service = _dataset_service()
    try:
        data = service.dataset_rows(user, dataset_id)
        conditions, filtered, count = analytics.apply_filter(
            data["columns"], data["rows"], expression
        )
    except ValueError as exc:
        return fail(str(exc))
    return ok(
        {
            "slug": "filter_builder",
            "preview_count": count,
            "matched_rows": filtered[:10],
            "columns": data["columns"],
        }
    )


@api_bp.route("/api/data/chart_builder/preview", methods=["GET"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def chart_preview():
    user = current_user()
    dataset_id = request.args.get("dataset_id", type=int)
    x = request.args.get("x", "")
    y = request.args.get("y", "")
    agg = request.args.get("agg", "sum")
    if not dataset_id:
        return fail("dataset_id is required")
    service = _dataset_service()
    try:
        data = service.dataset_rows(user, dataset_id)
        series = analytics.aggregate_chart(data["columns"], data["rows"], x, y, agg)
    except ValueError as exc:
        return fail(str(exc))
    return ok({"slug": "chart_builder", "series": series})


@api_bp.route("/api/data/calculated_columns/preview", methods=["GET"])
@login_required
@role_required(models.ROLE_ANALYST, models.ROLE_ADMIN)
def calculated_preview():
    user = current_user()
    dataset_id = request.args.get("dataset_id", type=int)
    expression = request.args.get("expression", "")
    if not dataset_id:
        return fail("dataset_id is required")
    service = _dataset_service()
    try:
        data = service.dataset_rows(user, dataset_id)
        sample = analytics.validate_calc_expression(
            data["columns"], data["rows"], expression
        )
    except ValueError as exc:
        return fail(str(exc))
    return ok({"slug": "calculated_columns", "sample": sample})
