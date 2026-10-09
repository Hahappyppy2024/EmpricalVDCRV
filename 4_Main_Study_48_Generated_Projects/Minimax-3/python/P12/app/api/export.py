"""DATA-09 — Export."""
from __future__ import annotations

import os

from flask import Blueprint, request, send_file

from ..extensions import db
from ..models import ExportRecord
from ..services import audit_service
from ..services.dataset_service import can_view, get_dataset_or_404
from ..services.export_service import export_dataset
from ..utils import (
    error_response,
    get_current_user,
    is_choice,
    is_int,
    is_safe_string,
    require_auth,
    success_response,
    ALL_ROLES,
)

bp = Blueprint("export", __name__)


@bp.get("/api/data/export")
@require_auth(roles=ALL_ROLES)
def list_exports():
    user = get_current_user()
    dataset_id = request.args.get("dataset_id")
    query = db.session.query(ExportRecord)
    if dataset_id:
        try:
            dataset_id_int = is_int(dataset_id, field="dataset_id", minimum=1)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        query = query.filter_by(dataset_id=dataset_id_int)
    records = query.order_by(ExportRecord.created_at.desc()).all()
    if user.role != "admin":
        records = [r for r in records if r.owner_id == user.id]
    return success_response({"exports": [r.to_dict() for r in records]})


@bp.post("/api/data/export")
@require_auth(roles=ALL_ROLES)
def create_export():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        export_format = is_choice(
            payload.get("format", "csv"),
            choices={"csv", "json", "pdf"},
            field="format",
        )
        filter_expression = (payload.get("filter_expression") or "").strip()
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset = get_dataset_or_404(dataset_id)
    if not can_view(dataset, user):
        return error_response("Dataset not visible", status=404, code="not_found")
    try:
        record = export_dataset(
            owner=user,
            dataset=dataset,
            export_format=export_format,
            filter_expression=filter_expression,
        )
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    audit_service.record_audit(
        actor=user,
        action="export.create",
        entity_type="export",
        entity_id=record.id,
        summary=f"{user.username} exported dataset {dataset.name} as {export_format}",
    )
    audit_service.record_lineage(
        parent_type="dataset",
        parent_id=dataset.id,
        child_type="export",
        child_id=record.id,
        transformation=f"export:{export_format}",
    )
    return success_response({"export": record.to_dict()}, status=201)


@bp.patch("/api/data/export/<int:export_id>")
@require_auth(roles=ALL_ROLES)
def update_export(export_id: int):
    user = get_current_user()
    record = db.session.get(ExportRecord, export_id)
    if record is None:
        return error_response("Export not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != "admin":
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    if "description" in payload:
        # We re-use status to flag custom metadata
        description = is_safe_string(payload.get("description", ""), max_length=255, field="description")
        record.status = f"tagged:{description[:32]}"
        db.session.commit()
    return success_response({"export": record.to_dict()})


@bp.get("/api/data/export/<int:export_id>/download")
@require_auth(roles=ALL_ROLES)
def download_export(export_id: int):
    user = get_current_user()
    record = db.session.get(ExportRecord, export_id)
    if record is None:
        return error_response("Export not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != "admin":
        return error_response("Insufficient privileges", status=403, code="forbidden")
    if not os.path.exists(record.file_path):
        return error_response("Stored export file is missing", status=404, code="not_found")
    audit_service.record_audit(
        actor=user,
        action="export.download",
        entity_type="export",
        entity_id=record.id,
        summary=f"{user.username} downloaded export",
    )
    download_name = os.path.basename(record.file_path)
    return send_file(record.file_path, as_attachment=True, download_name=download_name)
