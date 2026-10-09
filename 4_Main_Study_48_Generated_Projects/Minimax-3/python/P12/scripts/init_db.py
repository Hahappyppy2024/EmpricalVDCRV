"""Database initialization and deterministic seed fixtures."""
from __future__ import annotations

import csv
import json
import os
import shutil
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from app import create_app  # noqa: E402
from app.extensions import db  # noqa: E402
from app.models import (  # noqa: E402
    AdminSettings,
    CalculatedColumn,
    ChartSpec,
    DashboardShare,
    DataSource,
    Dataset,
    SavedFilterView,
    Session,
    User,
    utcnow,
)
from app.services.auth_service import hash_password  # noqa: E402


SEED_USERS = [
    {
        "username": "admin",
        "email": "admin@example.com",
        "display_name": "Ada Admin",
        "password": "Admin#123",
        "role": "admin",
    },
    {
        "username": "analyst",
        "email": "analyst@example.com",
        "display_name": "Alex Analyst",
        "password": "Analyst#123",
        "role": "analyst",
    },
    {
        "username": "analyst2",
        "email": "analyst2@example.com",
        "display_name": "Avery Analyst",
        "password": "Analyst#123",
        "role": "analyst",
    },
    {
        "username": "viewer",
        "email": "viewer@example.com",
        "display_name": "Vera Viewer",
        "password": "Viewer#123",
        "role": "viewer",
    },
]


def _write_sample_csv(path: Path, rows: list[dict]) -> None:
    if not rows:
        path.write_text("", encoding="utf-8")
        return
    fieldnames = list(rows[0].keys())
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def _write_sample_json(path: Path, rows: list[dict]) -> None:
    path.write_text(json.dumps(rows, indent=2), encoding="utf-8")


def _build_seed_dataset_files(upload_dir: Path) -> dict[str, Path]:
    upload_dir.mkdir(parents=True, exist_ok=True)
    sales_rows = [
        {"date": "2025-01-01", "region": "North", "product": "Alpha", "units": 12, "revenue": 360.0},
        {"date": "2025-01-02", "region": "North", "product": "Beta", "units": 8, "revenue": 224.0},
        {"date": "2025-01-03", "region": "South", "product": "Alpha", "units": 15, "revenue": 450.0},
        {"date": "2025-01-04", "region": "South", "product": "Beta", "units": 4, "revenue": 112.0},
        {"date": "2025-01-05", "region": "East", "product": "Gamma", "units": 9, "revenue": 405.0},
        {"date": "2025-01-06", "region": "East", "product": "Alpha", "units": 22, "revenue": 660.0},
        {"date": "2025-01-07", "region": "West", "product": "Gamma", "units": 5, "revenue": 225.0},
        {"date": "2025-01-08", "region": "West", "product": "Beta", "units": 17, "revenue": 476.0},
        {"date": "2025-01-09", "region": "North", "product": "Gamma", "units": 11, "revenue": 495.0},
        {"date": "2025-01-10", "region": "South", "product": "Alpha", "units": 13, "revenue": 390.0},
    ]
    logs_rows = [
        {"timestamp": "2025-01-01T08:00:00", "level": "INFO", "service": "api", "message": "Started"},
        {"timestamp": "2025-01-01T08:05:00", "level": "WARN", "service": "api", "message": "Slow response"},
        {"timestamp": "2025-01-01T09:00:00", "level": "ERROR", "service": "worker", "message": "Failed to connect"},
        {"timestamp": "2025-01-01T09:30:00", "level": "INFO", "service": "worker", "message": "Reconnected"},
        {"timestamp": "2025-01-01T10:00:00", "level": "INFO", "service": "api", "message": "Healthy"},
    ]
    sales_csv = upload_dir / "seed_sales.csv"
    logs_json = upload_dir / "seed_logs.json"
    _write_sample_csv(sales_csv, sales_rows)
    _write_sample_json(logs_json, logs_rows)
    return {"sales_csv": sales_csv, "logs_json": logs_json}


def _detect_schema(rows: list[dict]) -> list[dict]:
    schema: dict[str, str] = {}
    for row in rows:
        for key, value in row.items():
            if key in schema:
                continue
            if isinstance(value, bool):
                schema[key] = "boolean"
            elif isinstance(value, int):
                schema[key] = "integer"
            elif isinstance(value, float):
                schema[key] = "number"
            else:
                schema[key] = "string"
    return [{"name": k, "type": v} for k, v in schema.items()]


def _coerce_csv(value: str):
    stripped = (value or "").strip()
    if not stripped:
        return None
    lowered = stripped.lower()
    if lowered in {"true", "false"}:
        return lowered == "true"
    try:
        return int(stripped)
    except ValueError:
        pass
    try:
        return float(stripped)
    except ValueError:
        return stripped


def _load_rows(path: Path, kind: str) -> list[dict]:
    if kind == "csv":
        with path.open("r", encoding="utf-8", newline="") as handle:
            raw = list(csv.DictReader(handle))
        return [{k: _coerce_csv(v) for k, v in row.items() if k is not None} for row in raw]
    return json.loads(path.read_text(encoding="utf-8"))


def init_database(reset: bool = True) -> None:
    app = create_app()
    upload_dir = Path(app.config["UPLOAD_FOLDER"])
    export_dir = Path(app.config["EXPORT_FOLDER"])

    if reset:
        db_uri = app.config["SQLALCHEMY_DATABASE_URI"]
        if db_uri.startswith("sqlite:///"):
            db_path = Path(db_uri.replace("sqlite:///", "", 1))
            if db_path.exists():
                db_path.unlink()
        if upload_dir.exists():
            shutil.rmtree(upload_dir)
        if export_dir.exists():
            shutil.rmtree(export_dir)

    with app.app_context():
        db.create_all()
        upload_dir.mkdir(parents=True, exist_ok=True)
        export_dir.mkdir(parents=True, exist_ok=True)

        # Seed users
        users = {}
        for spec in SEED_USERS:
            user = User(
                username=spec["username"],
                email=spec["email"],
                display_name=spec["display_name"],
                password_hash=hash_password(spec["password"]),
                role=spec["role"],
                is_active=True,
            )
            db.session.add(user)
            users[spec["username"]] = user
        db.session.flush()

        # Seed datasets
        seed_files = _build_seed_dataset_files(upload_dir)
        sales_path = seed_files["sales_csv"]
        logs_path = seed_files["logs_json"]
        sales_rows = _load_rows(sales_path, "csv")
        logs_rows = _load_rows(logs_path, "json")

        sales_dataset = Dataset(
            name="Quarterly Sales",
            description="Daily regional product sales by Alpha, Beta, Gamma.",
            owner_id=users["analyst"].id,
            visibility="team",
            source_format="csv",
            file_path=sales_path.as_posix(),
            row_count=len(sales_rows),
            column_count=5,
            schema_json=json.dumps(_detect_schema(sales_rows)),
            tags_csv="sales,regional,kpi",
        )
        logs_dataset = Dataset(
            name="Service Logs",
            description="Sample log entries for service monitoring walkthroughs.",
            owner_id=users["analyst2"].id,
            visibility="public",
            source_format="json",
            file_path=logs_path.as_posix(),
            row_count=len(logs_rows),
            column_count=4,
            schema_json=json.dumps(_detect_schema(logs_rows)),
            tags_csv="logs,monitoring",
        )
        db.session.add_all([sales_dataset, logs_dataset])
        db.session.flush()

        # Seed filter view
        saved_filter = SavedFilterView(
            dataset_id=sales_dataset.id,
            owner_id=users["analyst"].id,
            name="High revenue North sales",
            expression="revenue > 400 and region == 'North'",
            description="North region sales exceeding 400 in revenue",
            is_public=True,
        )
        db.session.add(saved_filter)

        # Seed calculated column
        calc_column = CalculatedColumn(
            dataset_id=sales_dataset.id,
            owner_id=users["analyst"].id,
            name="avg_unit_price",
            expression="revenue / units",
            return_type="number",
            description="Average unit price per sale",
        )
        db.session.add(calc_column)

        # Seed chart
        chart_spec = ChartSpec(
            dataset_id=sales_dataset.id,
            owner_id=users["analyst"].id,
            name="Revenue by Region",
            chart_type="bar",
            config_json=json.dumps({"x": "region", "y": "revenue", "aggregate": "sum"}),
            filter_expression="units > 0",
        )
        db.session.add(chart_spec)

        # Seed dashboard share
        share = DashboardShare(
            dataset_id=sales_dataset.id,
            owner_id=users["analyst"].id,
            link_token="seedshare0000000000000000000000",
            audience="team",
            permission="view",
            description="Team-wide quarterly sales dashboard",
            revoked=False,
        )
        db.session.add(share)

        # Seed data sources
        ds_csv = DataSource(
            name="Sales CSV Source",
            kind="csv",
            description="Direct connection to the sales dataset",
            enabled=True,
            config_json=json.dumps({"dataset_id": sales_dataset.id, "path": sales_path.name}),
            created_by=users["admin"].id,
        )
        ds_api = DataSource(
            name="Mock API Source",
            kind="api_mock",
            description="Synthetic REST endpoint backed by the logs dataset",
            enabled=True,
            config_json=json.dumps({"dataset_id": logs_dataset.id, "endpoint": "/mock/api/logs"}),
            created_by=users["admin"].id,
        )
        db.session.add_all([ds_csv, ds_api])

        # Admin settings
        db.session.add_all(
            [
                AdminSettings(key="retention_days", value="90", updated_by=users["admin"].id),
                AdminSettings(key="max_datasets_per_user", value="25", updated_by=users["admin"].id),
                AdminSettings(key="share_default_ttl_hours", value="168", updated_by=users["admin"].id),
            ]
        )

        db.session.commit()

        print("Seed complete. Users:")
        for spec in SEED_USERS:
            print(f"  {spec['username']} / {spec['password']} ({spec['role']})")
        print(f"Datasets: {sales_dataset.name} ({sales_dataset.id}), {logs_dataset.name} ({logs_dataset.id})")


if __name__ == "__main__":
    reset_flag = "--no-reset" not in sys.argv
    init_database(reset=reset_flag)
