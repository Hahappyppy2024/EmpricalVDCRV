"""Deterministic seed fixtures.

Every actor, role, relationship, workflow state, and dependency required by
the acceptance criteria is created here. Re-running init-db resets the whole
database to exactly this state.
"""
import csv
import io
import json
import os
from datetime import datetime

from werkzeug.security import generate_password_hash

from . import analytics, models
from .config import Config

SEED_SALES_FILE = "retail_sales_seed.csv"
SEED_FEEDBACK_FILE = "customer_feedback_seed.json"
SEED_SHARE_TOKEN = "seed-share-link-001"


def seed(repo, config=None):
    config = config or Config()
    upload_dir = os.environ.get("UPLOAD_DIR", config.UPLOAD_DIR)
    os.makedirs(upload_dir, exist_ok=True)

    # -- settings ------------------------------------------------------------
    for key, value in (
        ("retention_days", "30"),
        ("max_datasets_per_analyst", "50"),
        ("max_upload_mb", str(config.MAX_UPLOAD_MB)),
    ):
        repo.add(models.Setting(key=key, value=value))

    # -- users ---------------------------------------------------------------
    admin = _user(repo, "admin", "admin@example.com", "admin123", models.ROLE_ADMIN)
    analyst = _user(repo, "analyst", "analyst@example.com", "analyst123", models.ROLE_ANALYST)
    viewer = _user(repo, "viewer", "viewer@example.com", "viewer123", models.ROLE_VIEWER)
    repo.flush()

    # -- deterministic sessions + account access log --------------------------
    repo.add(models.SessionRecord(user_id=admin.id, token="seed-session-admin-0001"))
    repo.add(models.SessionRecord(user_id=analyst.id, token="seed-session-analyst-0001"))
    repo.add(models.SessionRecord(user_id=viewer.id, token="seed-session-viewer-0001"))
    for user in (admin, analyst, viewer):
        repo.add(
            models.AccountAccess(
                user_id=user.id, action="signup", outcome="success", detail=user.username
            )
        )
        repo.add(
            models.AccountAccess(
                user_id=user.id, action="signin", outcome="success", detail=user.username
            )
        )

    # -- dataset 1: Retail Sales (CSV) ----------------------------------------
    sales_columns = ["region", "product", "sales", "quantity", "date"]
    sales_rows = [
        ["East", "Widget", 120.5, 3, "2026-01-05"],
        ["West", "Gadget", 230, 2, "2026-01-07"],
        ["North", "Widget", 90, 1, "2026-01-12"],
        ["South", "Gadget", 310.75, 4, "2026-02-03"],
        ["East", "Gizmo", 150, 2, "2026-02-14"],
        ["West", "Widget", 410, 5, "2026-03-02"],
        ["North", "Gadget", 60, 1, "2026-03-11"],
        ["South", "Gizmo", 275.5, 3, "2026-04-01"],
        ["East", "Gadget", 190, 2, "2026-04-20"],
        ["West", "Gizmo", 330, 4, "2026-05-09"],
    ]
    sales_csv = _to_csv(sales_columns, sales_rows)
    sales_dataset = _make_dataset(
        repo, analyst, "Retail Sales", "csv", sales_columns, sales_rows
    )
    sales_file = _write_file(repo, analyst, SEED_SALES_FILE, sales_csv, upload_dir)
    _make_upload(repo, analyst, sales_dataset, sales_file, SEED_SALES_FILE, "csv")
    _make_catalog(
        repo,
        analyst,
        sales_dataset,
        "Retail Sales",
        "Monthly retail sales by region and product.",
        "sales,regional,seed",
        "shared",
    )
    sales_preview = _make_preview(repo, analyst, sales_dataset)

    # -- dataset 2: Customer Feedback (JSON) ------------------------------------
    feedback_columns = ["customer", "rating", "channel", "comment"]
    feedback_rows = [
        ["alice", 5, "email", "Fast delivery"],
        ["bob", 3, "chat", "Average"],
        ["carol", 4, "phone", "Helpful support"],
        ["dave", 2, "email", "Late shipment"],
        ["erin", 5, "chat", "Excellent"],
        ["frank", 4, "email", "Good quality"],
        ["grace", 1, "phone", "Damaged item"],
        ["henry", 4, "chat", "Nice experience"],
    ]
    feedback_json = _to_json(feedback_columns, feedback_rows)
    feedback_dataset = _make_dataset(
        repo, analyst, "Customer Feedback", "json", feedback_columns, feedback_rows
    )
    feedback_file = _write_file(
        repo, analyst, SEED_FEEDBACK_FILE, feedback_json, upload_dir
    )
    _make_upload(repo, analyst, feedback_dataset, feedback_file, SEED_FEEDBACK_FILE, "json")
    _make_catalog(
        repo,
        analyst,
        feedback_dataset,
        "Customer Feedback",
        "Customer satisfaction ratings by channel.",
        "feedback,customers,seed",
        "private",
    )
    _make_preview(repo, analyst, feedback_dataset)

    # -- filter builder (DATA-05) ----------------------------------------------
    high_value = _make_filter(
        repo, analyst, sales_dataset, "High value sales", "sales > 200"
    )
    email_feedback = _make_filter(
        repo, analyst, feedback_dataset, "Email feedback", 'channel == "email"'
    )

    # -- chart builder (DATA-06) ------------------------------------------------
    sales_chart = _make_chart(
        repo, analyst, sales_dataset, "Sales by region", "bar", "region", "sales", "sum"
    )
    ratings_chart = _make_chart(
        repo, analyst, feedback_dataset, "Ratings by channel", "pie", "channel", "rating", "avg"
    )

    # -- calculated columns (DATA-07) --------------------------------------------
    _make_calculated(
        repo, analyst, sales_dataset, "Sales tax", "sales_tax", "sales * 0.1"
    )
    _make_calculated(
        repo, analyst, feedback_dataset, "Rating double", "rating_double", "rating * 2"
    )

    # -- dashboard sharing (DATA-08) ----------------------------------------------
    repo.add(
        models.DashboardSharing(
            owner_id=analyst.id,
            dataset_id=sales_dataset.id,
            chart_id=sales_chart.id,
            share_type="team",
            target="sales-team",
            link_token="",
        )
    )
    repo.add(
        models.DashboardSharing(
            owner_id=analyst.id,
            dataset_id=feedback_dataset.id,
            chart_id=ratings_chart.id,
            share_type="link",
            target="",
            link_token=SEED_SHARE_TOKEN,
        )
    )

    # -- export (DATA-09) ---------------------------------------------------------
    _make_export(repo, analyst, sales_dataset, "csv", "", upload_dir)
    _make_export(repo, analyst, sales_dataset, "pdf", "sales > 200", upload_dir)

    # -- data source connections (DATA-10) -----------------------------------------
    repo.add(
        models.DataSourceConnections(
            owner_id=analyst.id,
            name="Sales Warehouse",
            kind="mockdb",
            config_json={"seed": 7, "last_test": "2026-01-01T00:00:00Z", "sample_rows": 10},
            status="connected",
        )
    )
    repo.add(
        models.DataSourceConnections(
            owner_id=analyst.id,
            name="Rates API",
            kind="mockapi",
            config_json={"seed": 3, "last_test": "2026-01-01T00:00:00Z", "sample_rows": 10},
            status="connected",
        )
    )

    # -- audit and lineage (DATA-11) ------------------------------------------------
    lineage_entries = [
        (analyst, sales_dataset, "import", SEED_SALES_FILE, f"dataset:{sales_dataset.id}", "seed import of Retail Sales"),
        (analyst, sales_dataset, "filter", f"dataset:{sales_dataset.id}", f"filter:{high_value.id}", "sales > 200"),
        (analyst, sales_dataset, "chart", f"dataset:{sales_dataset.id}", f"chart:{sales_chart.id}", "bar region by sales"),
        (analyst, sales_dataset, "calculated", f"dataset:{sales_dataset.id}", "calculated:sales_tax", "sales_tax = sales * 0.1"),
        (analyst, sales_dataset, "export", f"dataset:{sales_dataset.id}", "export:csv", "csv export"),
        (analyst, sales_dataset, "share", f"dataset:{sales_dataset.id}", "share:sales-team", "team share to sales-team"),
        (analyst, feedback_dataset, "import", SEED_FEEDBACK_FILE, f"dataset:{feedback_dataset.id}", "seed import of Customer Feedback"),
        (analyst, feedback_dataset, "filter", f"dataset:{feedback_dataset.id}", f"filter:{email_feedback.id}", 'channel == "email"'),
        (analyst, feedback_dataset, "chart", f"dataset:{feedback_dataset.id}", f"chart:{ratings_chart.id}", "pie channel by rating"),
    ]
    for user, dataset, op, src, out, detail in lineage_entries:
        repo.add(
            models.AuditAndLineage(
                user_id=user.id,
                dataset_id=dataset.id,
                operation=op,
                source_ref=src,
                output_ref=out,
                detail=detail,
            )
        )

    event_entries = [
        (analyst, "dataset.upload", "dataset", sales_dataset.id, SEED_SALES_FILE),
        (analyst, "preview.run", "dataset", sales_dataset.id, "Retail Sales"),
        (analyst, "filter.create", "filter_builder", high_value.id, "High value sales"),
        (analyst, "chart.create", "chart_builder", sales_chart.id, "Sales by region"),
        (analyst, "calculated.create", "calculated_columns", None, "sales_tax = sales * 0.1"),
        (analyst, "export.create", "export", None, "csv Retail Sales"),
        (analyst, "share.create", "dashboard_sharing", None, "team:sales-team"),
        (analyst, "source.test", "data_source_connections", None, "connected"),
        (analyst, "dataset.upload", "dataset", feedback_dataset.id, SEED_FEEDBACK_FILE),
        (admin, "auth.signin", "user", admin.id, "admin"),
        (viewer, "auth.signin", "user", viewer.id, "viewer"),
    ]
    for user, action, etype, eid, detail in event_entries:
        repo.add(
            models.AuditEvent(
                user_id=user.id,
                action=action,
                entity_type=etype,
                entity_id=eid,
                detail=detail,
            )
        )

    # -- admin operations (DATA-12) ---------------------------------------------------
    admin_ops = [
        ("setting.update", "max_datasets_per_analyst", "max_datasets_per_analyst -> 50"),
        ("retention.run", "30", "retention run over 30 days: 0 sessions, 0 audit events removed"),
        ("setting.update", "max_upload_mb", "max_upload_mb -> 10"),
    ]
    for action, target, detail in admin_ops:
        record = repo.add(
            models.AdminOperations(
                admin_id=admin.id, action=action, target=target, detail=detail
            )
        )
        repo.add(
            models.AuditEvent(
                user_id=admin.id,
                action="admin." + action,
                entity_type="admin_operations",
                entity_id=record.id,
                detail=detail,
            )
        )

    return {
        "users": 3,
        "datasets": 2,
        "uploads": 2,
        "catalog": 2,
        "previews": 2,
        "filters": 2,
        "charts": 2,
        "calculated": 2,
        "shares": 2,
        "exports": 2,
        "sources": 2,
        "lineage": len(lineage_entries),
        "events": len(event_entries),
        "admin_ops": len(admin_ops),
    }


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _user(repo, username, email, password, role):
    return repo.add(
        models.User(
            username=username,
            email=email,
            password_hash=generate_password_hash(password),
            role=role,
            enabled=True,
        )
    )


def _make_dataset(repo, user, name, fmt, columns, rows):
    dataset = repo.add(
        models.Dataset(
            owner_id=user.id,
            name=name,
            format=fmt,
            data_json={"columns": columns, "rows": rows},
            row_count=len(rows),
            column_count=len(columns),
        )
    )
    repo.flush()
    return dataset


def _write_file(repo, user, filename, content, upload_dir, kind="upload"):
    path = os.path.join(upload_dir, filename)
    payload = content if isinstance(content, bytes) else content.encode("utf-8")
    with open(path, "wb") as handle:
        handle.write(payload)
    stored = repo.add(
        models.StoredFile(
            owner_id=user.id,
            filename=filename,
            storage_path=path,
            kind=kind,
            file_size=len(payload),
        )
    )
    repo.flush()
    return stored


def _make_upload(repo, user, dataset, stored, filename, fmt):
    record = repo.add(
        models.DatasetUpload(
            owner_id=user.id,
            dataset_id=dataset.id,
            file_id=stored.id,
            filename=filename,
            format=fmt,
            rows=dataset.row_count,
            columns=dataset.column_count,
            status="stored",
        )
    )
    repo.flush()
    return record


def _make_catalog(repo, user, dataset, name, description, tags, visibility):
    record = repo.add(
        models.DatasetCatalog(
            owner_id=user.id,
            dataset_id=dataset.id,
            name=name,
            description=description,
            tags=tags,
            visibility=visibility,
        )
    )
    repo.flush()
    return record


def _make_preview(repo, user, dataset):
    columns = dataset.data_json["columns"]
    rows = dataset.data_json["rows"]
    summary = analytics.summarize(columns, rows)
    preview = {
        "columns": columns,
        "rows": rows[:50],
        "row_count": len(rows),
        "column_count": len(columns),
    }
    record = repo.add(
        models.DataPreview(
            user_id=user.id,
            dataset_id=dataset.id,
            summary_json=summary,
            preview_json=preview,
        )
    )
    repo.flush()
    return record


def _make_filter(repo, user, dataset, name, expression):
    columns = dataset.data_json["columns"]
    rows = dataset.data_json["rows"]
    conditions, filtered, count = analytics.apply_filter(columns, rows, expression)
    record = repo.add(
        models.FilterBuilder(
            owner_id=user.id,
            dataset_id=dataset.id,
            name=name,
            expression=expression,
            filter_json={"conditions": conditions, "count": count},
            row_count=count,
        )
    )
    repo.flush()
    return record


def _make_chart(repo, user, dataset, name, chart_type, x, y, agg):
    columns = dataset.data_json["columns"]
    rows = dataset.data_json["rows"]
    series = analytics.aggregate_chart(columns, rows, x, y, agg)
    record = repo.add(
        models.ChartBuilder(
            owner_id=user.id,
            dataset_id=dataset.id,
            name=name,
            chart_type=chart_type,
            config_json={"x": x, "y": y, "agg": agg, "chart_type": chart_type, "series": series},
        )
    )
    repo.flush()
    return record


def _make_calculated(repo, user, dataset, name, column_name, expression):
    sample = analytics.validate_calc_expression(
        dataset.data_json["columns"], dataset.data_json["rows"], expression
    )
    record = repo.add(
        models.CalculatedColumns(
            owner_id=user.id,
            dataset_id=dataset.id,
            name=name,
            column_name=column_name,
            expression=expression,
        )
    )
    repo.flush()
    return record


def _make_export(repo, user, dataset, kind, filter_ref, upload_dir):
    columns = dataset.data_json["columns"]
    rows = dataset.data_json["rows"]
    if filter_ref:
        conditions, rows, count = analytics.apply_filter(columns, rows, filter_ref)
    base = dataset.name.replace(" ", "_")
    if kind == "csv":
        payload = analytics.build_csv(columns, rows)
        filename = f"seed_{base}.csv"
    else:
        lines = [
            "Data Analytics Dashboard - Export Report",
            "",
            f"Dataset: {dataset.name}",
            f"Rows: {len(rows)}   Columns: {len(columns)}",
        ]
        if filter_ref:
            lines.append(f"Filter: {filter_ref}")
        lines.append("")
        lines.append("Data preview (first 25 rows):")
        lines.append(" | ".join(columns))
        for row in rows[:25]:
            lines.append(" | ".join(str(v) if v is not None else "" for v in row))
        payload = analytics.build_pdf(f"Seed export of {dataset.name}", lines)
        filename = f"seed_{base}.pdf"
    stored = _write_file(repo, user, filename, payload, upload_dir, kind="export")
    record = repo.add(
        models.Export(
            owner_id=user.id,
            dataset_id=dataset.id,
            kind=kind,
            filter_ref=filter_ref,
            file_id=stored.id,
            status="ready",
        )
    )
    repo.flush()
    return record


def _to_csv(columns, rows):
    buffer = io.StringIO()
    writer = csv.writer(buffer)
    writer.writerow(columns)
    for row in rows:
        writer.writerow(row)
    return buffer.getvalue()


def _to_json(columns, rows):
    payload = [dict(zip(columns, row)) for row in rows]
    return json.dumps(payload, indent=2)


if __name__ == "__main__":
    from .data_access import Repository
    from .extensions import Base, init_db_engine
    from . import extensions as _ext

    init_db_engine(Config.DATABASE_PATH)
    engine = _ext.SessionLocal().get_bind()
    Base.metadata.drop_all(engine)
    Base.metadata.create_all(engine)
    session = _ext.SessionLocal()
    try:
        counts = seed(Repository(session))
        session.commit()
        print("Seeded counts:", counts)
    except Exception as exc:  # noqa: BLE001
        session.rollback()
        raise
    finally:
        session.close()
