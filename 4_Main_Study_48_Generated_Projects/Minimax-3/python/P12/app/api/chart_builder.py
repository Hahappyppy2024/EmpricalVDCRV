"""DATA-06 — Chart builder."""
from __future__ import annotations

import json

from flask import Blueprint, request

from ..extensions import db
from ..models import ChartSpec
from ..services import audit_service
from ..services.chart_service import ALLOWED_CHART_TYPES, build_chart_payload, chart_to_dict
from ..services.dataset_service import (
    can_modify,
    can_view,
    get_dataset_or_404,
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

bp = Blueprint("chart_builder", __name__)


@bp.get("/api/data/chart_builder")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def list_charts():
    user = get_current_user()
    dataset_id = request.args.get("dataset_id")
    query = db.session.query(ChartSpec)
    if dataset_id:
        try:
            dataset_id_int = is_int(dataset_id, field="dataset_id", minimum=1)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        query = query.filter_by(dataset_id=dataset_id_int)
    charts = query.order_by(ChartSpec.updated_at.desc()).all()
    if user.role != ROLE_ADMIN:
        charts = [c for c in charts if c.owner_id == user.id]
    return success_response({"charts": [chart_to_dict(c) for c in charts]})


@bp.post("/api/data/chart_builder")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def create_chart():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
        chart_type = is_choice(
            payload.get("chart_type", "bar"),
            choices=ALLOWED_CHART_TYPES,
            field="chart_type",
        )
        config = payload.get("config") or {}
        if not isinstance(config, dict):
            return error_response("config must be an object", status=400, code="bad_request")
        filter_expression = (payload.get("filter_expression") or "").strip()
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset = get_dataset_or_404(dataset_id)
    if not can_view(dataset, user):
        return error_response("Dataset not visible", status=404, code="not_found")
    try:
        computed = build_chart_payload(dataset, chart_type, config, filter_expression)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")

    record = ChartSpec(
        dataset_id=dataset.id,
        owner_id=user.id,
        name=name,
        chart_type=chart_type,
        config_json=json.dumps(config),
        filter_expression=filter_expression,
    )
    db.session.add(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="chart.create",
        entity_type="chart",
        entity_id=record.id,
        summary=f"{user.username} created chart '{name}'",
    )
    audit_service.record_lineage(
        parent_type="dataset",
        parent_id=dataset.id,
        child_type="chart",
        child_id=record.id,
        transformation="chart",
    )
    response = record.to_dict()
    response["computed"] = computed
    return success_response({"chart": response}, status=201)


@bp.patch("/api/data/chart_builder/<int:chart_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def update_chart(chart_id: int):
    user = get_current_user()
    record = db.session.get(ChartSpec, chart_id)
    if record is None:
        return error_response("Chart not found", status=404, code="not_found")
    if not can_modify(record.dataset, user) or (record.owner_id != user.id and user.role != ROLE_ADMIN):
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    if "name" in payload:
        record.name = is_safe_string(payload.get("name", ""), max_length=128, field="name")
    if "chart_type" in payload:
        record.chart_type = is_choice(
            payload.get("chart_type", record.chart_type),
            choices=ALLOWED_CHART_TYPES,
            field="chart_type",
        )
    if "config" in payload:
        config = payload.get("config") or {}
        if not isinstance(config, dict):
            return error_response("config must be an object", status=400, code="bad_request")
        record.config_json = json.dumps(config)
    if "filter_expression" in payload:
        record.filter_expression = (payload.get("filter_expression") or "").strip()
    db.session.commit()
    try:
        computed = build_chart_payload(
            record.dataset,
            record.chart_type,
            json.loads(record.config_json),
            record.filter_expression,
        )
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    audit_service.record_audit(
        actor=user,
        action="chart.update",
        entity_type="chart",
        entity_id=record.id,
        summary=f"{user.username} updated chart",
    )
    response = record.to_dict()
    response["computed"] = computed
    return success_response({"chart": response})


@bp.delete("/api/data/chart_builder/<int:chart_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def delete_chart(chart_id: int):
    user = get_current_user()
    record = db.session.get(ChartSpec, chart_id)
    if record is None:
        return error_response("Chart not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != ROLE_ADMIN:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    db.session.delete(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="chart.delete",
        entity_type="chart",
        entity_id=chart_id,
        summary=f"{user.username} deleted chart",
    )
    return success_response({"deleted": chart_id})
