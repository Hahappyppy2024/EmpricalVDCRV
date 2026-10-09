"""DATA-10 — Data source connections."""
from __future__ import annotations

import json
from datetime import datetime

from flask import Blueprint, current_app, request

from ..extensions import db
from ..models import DataSource, Dataset
from ..services import audit_service
from ..services.dataset_service import can_modify, get_dataset_or_404, load_dataset_rows
from ..services.expression_engine import summarize
from ..utils import (
    error_response,
    get_current_user,
    is_bool,
    is_choice,
    is_safe_string,
    require_auth,
    success_response,
    ROLE_ANALYST,
    ROLE_ADMIN,
)


bp = Blueprint("data_source_connections", __name__)


@bp.get("/api/data/data_source_connections")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def list_sources():
    user = get_current_user()
    query = db.session.query(DataSource).order_by(DataSource.created_at.desc())
    if not current_app.config.get("EXPOSE_DATA_SOURCES_TO_VIEWERS") and user.role != ROLE_ADMIN:
        return success_response({"data_sources": []})
    sources = query.all()
    return success_response({"data_sources": [s.to_dict() for s in sources]})


@bp.post("/api/data/data_source_connections")
@require_auth(roles={ROLE_ADMIN, ROLE_ANALYST})
def create_source():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
        kind = is_choice(
            payload.get("kind", "csv"),
            choices={"csv", "json", "api_mock", "db_mock"},
            field="kind",
        )
        description = (payload.get("description") or "").strip()
        enabled = is_bool(payload.get("enabled", True), field="enabled")
        config = payload.get("config") or {}
        if not isinstance(config, dict):
            return error_response("config must be an object", status=400, code="bad_request")
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    existing = db.session.query(DataSource).filter_by(name=name).first()
    if existing:
        return error_response("Data source name already exists", status=409, code="conflict")
    record = DataSource(
        name=name,
        kind=kind,
        description=description,
        enabled=enabled,
        config_json=json.dumps(config),
        created_by=user.id,
    )
    db.session.add(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="datasource.create",
        entity_type="data_source",
        entity_id=record.id,
        summary=f"{user.username} registered source '{name}'",
    )
    return success_response({"data_source": record.to_dict()}, status=201)


@bp.patch("/api/data/data_source_connections/<int:source_id>")
@require_auth(roles={ROLE_ADMIN, ROLE_ANALYST})
def update_source(source_id: int):
    user = get_current_user()
    record = db.session.get(DataSource, source_id)
    if record is None:
        return error_response("Data source not found", status=404, code="not_found")
    payload = request.get_json(silent=True) or {}
    if "name" in payload:
        record.name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
    if "kind" in payload:
        record.kind = is_choice(
            payload.get("kind", record.kind),
            choices={"csv", "json", "api_mock", "db_mock"},
            field="kind",
        )
    if "description" in payload:
        record.description = (payload.get("description") or "").strip()
    if "enabled" in payload:
        record.enabled = is_bool(payload.get("enabled", record.enabled), field="enabled")
    if "config" in payload:
        config = payload.get("config") or {}
        if not isinstance(config, dict):
            return error_response("config must be an object", status=400, code="bad_request")
        record.config_json = json.dumps(config)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="datasource.update",
        entity_type="data_source",
        entity_id=record.id,
        summary=f"{user.username} updated data source",
    )
    return success_response({"data_source": record.to_dict()})


@bp.delete("/api/data/data_source_connections/<int:source_id>")
@require_auth(roles={ROLE_ADMIN})
def delete_source(source_id: int):
    user = get_current_user()
    record = db.session.get(DataSource, source_id)
    if record is None:
        return error_response("Data source not found", status=404, code="not_found")
    db.session.delete(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="datasource.delete",
        entity_type="data_source",
        entity_id=source_id,
        summary=f"{user.username} deleted data source",
    )
    return success_response({"deleted": source_id})


@bp.post("/api/data/data_source_connections/<int:source_id>/probe")
@require_auth(roles={ROLE_ADMIN, ROLE_ANALYST})
def probe_source(source_id: int):
    """Probe a source: returns row count, schema, and a small sample."""
    user = get_current_user()
    record = db.session.get(DataSource, source_id)
    if record is None:
        return error_response("Data source not found", status=404, code="not_found")
    if not record.enabled:
        return error_response("Data source is disabled", status=400, code="bad_request")
    config = json.loads(record.config_json)
    dataset_id_value = config.get("dataset_id")
    if not isinstance(dataset_id_value, int):
        return error_response(
            "Mock data sources must reference a dataset via config.dataset_id",
            status=400,
            code="bad_request",
        )
    dataset = db.session.get(Dataset, dataset_id_value)
    if dataset is None:
        return error_response("Linked dataset not found", status=404, code="not_found")
    if user.role != ROLE_ADMIN and not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")

    rows = load_dataset_rows(dataset, max_rows=10)
    summary = summarize(load_dataset_rows(dataset), json.loads(dataset.schema_json))
    audit_service.record_audit(
        actor=user,
        action="datasource.probe",
        entity_type="data_source",
        entity_id=record.id,
        summary=f"{user.username} probed source '{record.name}'",
    )
    return success_response(
        {
            "data_source": record.to_dict(),
            "dataset": dataset.to_summary(),
            "sample": rows,
            "summary": summary,
            "probed_at": datetime.utcnow().isoformat() + "Z",
        }
    )
