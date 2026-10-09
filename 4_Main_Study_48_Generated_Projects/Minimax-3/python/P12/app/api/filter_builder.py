"""DATA-05 — Filter builder: create, persist, run saved views."""
from __future__ import annotations

import json

from flask import Blueprint, request

from ..extensions import db
from ..models import SavedFilterView
from ..services import audit_service
from ..services.dataset_service import (
    can_view,
    get_dataset_or_404,
    load_dataset_rows,
)
from ..services.expression_engine import apply_filter, validate_expression
from ..utils import (
    error_response,
    get_current_user,
    is_bool,
    is_int,
    is_safe_string,
    require_auth,
    success_response,
    ALL_ROLES,
    ROLE_ANALYST,
    ROLE_ADMIN,
)

bp = Blueprint("filter_builder", __name__)


def _resolve_dataset_or_error(user, dataset_id):
    dataset = get_dataset_or_404(dataset_id)
    if not can_view(dataset, user):
        return None, error_response("Dataset not visible", status=404, code="not_found")
    return dataset, None


@bp.get("/api/data/filter_builder")
@require_auth(roles=ALL_ROLES)
def list_filters():
    user = get_current_user()
    dataset_id = request.args.get("dataset_id")
    query = db.session.query(SavedFilterView)
    if dataset_id:
        try:
            dataset_id_int = is_int(dataset_id, field="dataset_id", minimum=1)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        query = query.filter_by(dataset_id=dataset_id_int)
    filters = query.order_by(SavedFilterView.updated_at.desc()).all()
    if user.role != "admin":
        filters = [f for f in filters if f.owner_id == user.id or f.is_public]
    return success_response({"filters": [f.to_dict() for f in filters]})


@bp.post("/api/data/filter_builder")
@require_auth(roles=ALL_ROLES)
def create_filter():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
        expression = is_safe_string(payload.get("expression", ""), max_length=2000, field="expression")
        description = (payload.get("description") or "").strip()
        is_public = is_bool(payload.get("is_public", False), field="is_public")
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset, error = _resolve_dataset_or_error(user, dataset_id)
    if error is not None:
        return error
    if user.role not in {ROLE_ANALYST, ROLE_ADMIN, "viewer"}:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    schema_columns = {col["name"] for col in json.loads(dataset.schema_json)}
    try:
        validate_expression(expression, schema_columns)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")

    existing = (
        db.session.query(SavedFilterView)
        .filter_by(dataset_id=dataset.id, name=name)
        .first()
    )
    if existing is not None:
        existing.expression = expression
        existing.description = description
        existing.is_public = is_public and user.role in {ROLE_ANALYST, ROLE_ADMIN}
        record = existing
    else:
        record = SavedFilterView(
            dataset_id=dataset.id,
            owner_id=user.id,
            name=name,
            expression=expression,
            description=description,
            is_public=is_public and user.role in {ROLE_ANALYST, ROLE_ADMIN},
        )
        db.session.add(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="filter.save",
        entity_type="filter",
        entity_id=record.id,
        summary=f"{user.username} saved filter '{name}'",
    )
    audit_service.record_lineage(
        parent_type="dataset",
        parent_id=dataset.id,
        child_type="filter",
        child_id=record.id,
        transformation="filter",
    )
    return success_response({"filter": record.to_dict()}, status=201)


@bp.patch("/api/data/filter_builder/<int:filter_id>")
@require_auth(roles=ALL_ROLES)
def patch_filter(filter_id: int):
    user = get_current_user()
    record = db.session.get(SavedFilterView, filter_id)
    if record is None:
        return error_response("Filter not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role not in {ROLE_ANALYST, ROLE_ADMIN}:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    if "name" in payload:
        record.name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
    if "expression" in payload:
        expression = is_safe_string(payload.get("expression", ""), max_length=2000, field="expression")
        dataset = get_dataset_or_404(record.dataset_id)
        schema_columns = {col["name"] for col in json.loads(dataset.schema_json)}
        try:
            validate_expression(expression, schema_columns)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        record.expression = expression
    if "description" in payload:
        record.description = (payload.get("description") or "").strip()
    if "is_public" in payload and user.role in {ROLE_ANALYST, ROLE_ADMIN}:
        record.is_public = is_bool(payload.get("is_public", False), field="is_public")
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="filter.update",
        entity_type="filter",
        entity_id=record.id,
        summary=f"{user.username} updated filter",
    )
    return success_response({"filter": record.to_dict()})


@bp.delete("/api/data/filter_builder/<int:filter_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def delete_filter(filter_id: int):
    user = get_current_user()
    record = db.session.get(SavedFilterView, filter_id)
    if record is None:
        return error_response("Filter not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != "admin":
        return error_response("Insufficient privileges", status=403, code="forbidden")
    db.session.delete(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="filter.delete",
        entity_type="filter",
        entity_id=filter_id,
        summary=f"{user.username} deleted filter",
    )
    return success_response({"deleted": filter_id})


@bp.post("/api/data/filter_builder/run")
@require_auth(roles=ALL_ROLES)
def run_filter():
    """Run an ad-hoc or saved filter and return the resulting rows."""
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        expression = is_safe_string(payload.get("expression", ""), max_length=2000, field="expression")
        limit = is_int(payload.get("limit", 100), field="limit", minimum=1, maximum=1000)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset, error = _resolve_dataset_or_error(user, dataset_id)
    if error is not None:
        return error
    schema_columns = {col["name"] for col in json.loads(dataset.schema_json)}
    try:
        validate_expression(expression, schema_columns)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    rows = load_dataset_rows(dataset)
    matched = apply_filter(rows, expression, schema_columns)
    audit_service.record_audit(
        actor=user,
        action="filter.run",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} ran filter",
    )
    return success_response(
        {
            "dataset_id": dataset.id,
            "matched": len(matched),
            "rows": matched[:limit],
            "expression": expression,
            "total_rows": len(rows),
        }
    )
