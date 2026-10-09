"""DATA-02 — Dataset upload."""
from __future__ import annotations

import json
import os

from flask import Blueprint, request, send_file

from ..extensions import db
from ..models import Dataset, LineageRecord
from ..services import audit_service
from ..services.dataset_service import can_modify, get_dataset_or_404, persist_upload
from ..utils import (
    ApiError,
    error_response,
    get_current_user,
    is_choice,
    is_safe_string,
    require_auth,
    success_response,
    ROLE_ANALYST,
    ROLE_ADMIN,
)

bp = Blueprint("dataset_upload", __name__)


@bp.get("/api/data/dataset_upload")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def list_uploads():
    user = get_current_user()
    datasets = db.session.query(Dataset).order_by(Dataset.created_at.desc()).all()
    if user.role != "admin":
        datasets = [d for d in datasets if d.owner_id == user.id or d.visibility != "private"]
    return success_response({"datasets": [d.to_summary() for d in datasets]})


@bp.post("/api/data/dataset_upload")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def create_upload():
    user = get_current_user()
    if request.content_type and request.content_type.startswith("multipart/"):
        file_storage = request.files.get("file")
        if file_storage is None or not file_storage.filename:
            return error_response("Missing uploaded file", status=400, code="bad_request")
        try:
            name = is_safe_string(request.form.get("name", file_storage.filename), max_length=128, field="name")
            description = (request.form.get("description") or "").strip()
            visibility = is_choice(
                request.form.get("visibility", "private"),
                choices={"private", "team", "public"},
                field="visibility",
            )
            tags_raw = request.form.get("tags", "")
            tags = [t.strip() for t in tags_raw.split(",") if t.strip()]
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)
        try:
            dataset = persist_upload(
                owner=user,
                name=name,
                description=description,
                visibility=visibility,
                tags=tags,
                file_storage=file_storage,
            )
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)
    else:
        payload = request.get_json(silent=True) or {}
        try:
            name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
            description = (payload.get("description") or "").strip()
            visibility = is_choice(
                payload.get("visibility", "private"),
                choices={"private", "team", "public"},
                field="visibility",
            )
            tags = payload.get("tags") or []
            if not isinstance(tags, list):
                return error_response("tags must be a list", status=400, code="bad_request")
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)
        # JSON path also expects a stored file pointer (used for admin/seed flows)
        file_storage = request.files.get("file") if request.files else None
        if file_storage is None:
            return error_response("File upload is required", status=400, code="bad_request")
        try:
            dataset = persist_upload(
                owner=user,
                name=name,
                description=description,
                visibility=visibility,
                tags=tags,
                file_storage=file_storage,
            )
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)

    audit_service.record_audit(
        actor=user,
        action="dataset.upload",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} uploaded '{dataset.name}' ({dataset.row_count} rows)",
    )
    audit_service.record_lineage(
        parent_type="user",
        parent_id=user.id,
        child_type="dataset",
        child_id=dataset.id,
        transformation="upload",
    )
    return success_response({"dataset": dataset.to_summary()}, status=201)


@bp.patch("/api/data/dataset_upload/<int:dataset_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def update_upload(dataset_id: int):
    user = get_current_user()
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    if "name" in payload:
        dataset.name = is_safe_string(payload["name"], max_length=128, field="name")
    if "description" in payload:
        dataset.description = (payload.get("description") or "").strip()
    if "visibility" in payload:
        dataset.visibility = is_choice(
            payload["visibility"], choices={"private", "team", "public"}, field="visibility"
        )
    if "tags" in payload:
        tags = payload["tags"] or []
        if not isinstance(tags, list):
            return error_response("tags must be a list", status=400, code="bad_request")
        dataset.tags_csv = ",".join(sorted({str(t).strip().lower() for t in tags if str(t).strip()}))
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="dataset.update",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} updated dataset metadata",
    )
    return success_response({"dataset": dataset.to_summary()})


@bp.delete("/api/data/dataset_upload/<int:dataset_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def delete_upload(dataset_id: int):
    user = get_current_user()
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")
    file_path = dataset.file_path
    db.session.delete(dataset)
    db.session.commit()
    if file_path and os.path.exists(file_path):
        try:
            os.remove(file_path)
        except OSError:
            pass
    audit_service.record_audit(
        actor=user,
        action="dataset.delete",
        entity_type="dataset",
        entity_id=dataset_id,
        summary=f"{user.username} deleted dataset",
    )
    return success_response({"deleted": dataset_id})


@bp.get("/api/data/dataset_upload/<int:dataset_id>/file")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def download_upload(dataset_id: int):
    user = get_current_user()
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")
    if not os.path.exists(dataset.file_path):
        return error_response("Stored file is missing", status=404, code="not_found")
    return send_file(dataset.file_path, as_attachment=True, download_name=dataset.name + os.path.splitext(dataset.file_path)[1])
