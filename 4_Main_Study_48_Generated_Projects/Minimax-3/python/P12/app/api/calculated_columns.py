"""DATA-07 — Calculated columns."""
from __future__ import annotations

import json

from flask import Blueprint, request

from ..extensions import db
from ..models import CalculatedColumn
from ..services import audit_service
from ..services.dataset_service import can_modify, get_dataset_or_404, load_dataset_rows
from ..services.expression_engine import (
    compute_calculated_columns,
    validate_expression,
)
from ..utils import (
    error_response,
    get_current_user,
    is_choice,
    is_int,
    is_safe_string,
    require_auth,
    success_response,
    ROLE_ANALYST,
    ROLE_ADMIN,
)


bp = Blueprint("calculated_columns", __name__)


@bp.get("/api/data/calculated_columns")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def list_columns():
    user = get_current_user()
    dataset_id = request.args.get("dataset_id")
    query = db.session.query(CalculatedColumn)
    if dataset_id:
        try:
            dataset_id_int = is_int(dataset_id, field="dataset_id", minimum=1)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        query = query.filter_by(dataset_id=dataset_id_int)
    columns = query.order_by(CalculatedColumn.created_at.asc()).all()
    if user.role != ROLE_ADMIN:
        columns = [c for c in columns if c.owner_id == user.id]
    return success_response({"calculated_columns": [c.to_dict() for c in columns]})


@bp.post("/api/data/calculated_columns")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def create_column():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        name = is_safe_string(payload.get("name", ""), max_length=64, field="name")
        expression = is_safe_string(payload.get("expression", ""), max_length=2000, field="expression")
        return_type = is_choice(
            payload.get("return_type", "number"),
            choices={"number", "string", "boolean"},
            field="return_type",
        )
        description = (payload.get("description") or "").strip()
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")
    schema_columns = {col["name"] for col in json.loads(dataset.schema_json)}
    try:
        validate_expression(expression, schema_columns)
        rows = load_dataset_rows(dataset)
        compute_calculated_columns(rows[:5], [(name, expression)], schema_columns)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")

    existing = (
        db.session.query(CalculatedColumn)
        .filter_by(dataset_id=dataset.id, name=name)
        .first()
    )
    if existing:
        existing.expression = expression
        existing.return_type = return_type
        existing.description = description
        record = existing
    else:
        record = CalculatedColumn(
            dataset_id=dataset.id,
            owner_id=user.id,
            name=name,
            expression=expression,
            return_type=return_type,
            description=description,
        )
        db.session.add(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="calc.create",
        entity_type="calculated_column",
        entity_id=record.id,
        summary=f"{user.username} added calculated column '{name}'",
    )
    audit_service.record_lineage(
        parent_type="dataset",
        parent_id=dataset.id,
        child_type="calculated_column",
        child_id=record.id,
        transformation="calculated_column",
    )
    return success_response({"calculated_column": record.to_dict()}, status=201)


@bp.patch("/api/data/calculated_columns/<int:column_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def update_column(column_id: int):
    user = get_current_user()
    record = db.session.get(CalculatedColumn, column_id)
    if record is None:
        return error_response("Calculated column not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != ROLE_ADMIN:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    dataset = get_dataset_or_404(record.dataset_id)
    schema_columns = {col["name"] for col in json.loads(dataset.schema_json)}
    if "name" in payload:
        record.name = is_safe_string(payload.get("name", ""), max_length=64, field="name")
    if "expression" in payload:
        expression = is_safe_string(payload.get("expression", ""), max_length=2000, field="expression")
        try:
            validate_expression(expression, schema_columns)
            rows = load_dataset_rows(dataset)
            compute_calculated_columns(rows[:5], [(record.name, expression)], schema_columns)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        record.expression = expression
    if "return_type" in payload:
        record.return_type = is_choice(
            payload.get("return_type", record.return_type),
            choices={"number", "string", "boolean"},
            field="return_type",
        )
    if "description" in payload:
        record.description = (payload.get("description") or "").strip()
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="calc.update",
        entity_type="calculated_column",
        entity_id=record.id,
        summary=f"{user.username} updated calculated column",
    )
    return success_response({"calculated_column": record.to_dict()})


@bp.delete("/api/data/calculated_columns/<int:column_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def delete_column(column_id: int):
    user = get_current_user()
    record = db.session.get(CalculatedColumn, column_id)
    if record is None:
        return error_response("Calculated column not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != ROLE_ADMIN:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    db.session.delete(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="calc.delete",
        entity_type="calculated_column",
        entity_id=column_id,
        summary=f"{user.username} deleted calculated column",
    )
    return success_response({"deleted": column_id})
