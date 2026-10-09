"""DATA-11 — Audit and lineage."""
from __future__ import annotations

from flask import Blueprint, request

from ..services import audit_service
from ..utils import (
    error_response,
    is_int,
    require_auth,
    success_response,
    ROLE_ANALYST,
    ROLE_ADMIN,
)

bp = Blueprint("audit_and_lineage", __name__)


@bp.get("/api/data/audit_and_lineage")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def get_audit_and_lineage():
    entity_type = request.args.get("entity_type") or ""
    entity_id = request.args.get("entity_id") or ""
    limit = 200
    try:
        limit_value = request.args.get("limit")
        if limit_value is not None:
            limit = is_int(limit_value, field="limit", minimum=1, maximum=1000)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    events = audit_service.list_audit(limit)
    if entity_type:
        events = [e for e in events if e["entity_type"] == entity_type]
    if entity_id:
        events = [e for e in events if e["entity_id"] == str(entity_id)]
    lineage = audit_service.list_lineage()
    if entity_type:
        lineage = [r for r in lineage if r["parent_type"] == entity_type or r["child_type"] == entity_type]
    if entity_id:
        lineage = [
            r for r in lineage if r["parent_id"] == str(entity_id) or r["child_id"] == str(entity_id)
        ]
    return success_response({"audit": events, "lineage": lineage, "count": len(events)})


@bp.post("/api/data/audit_and_lineage")
@require_auth(roles={ROLE_ADMIN, ROLE_ANALYST})
def post_audit_entry():
    payload = request.get_json(silent=True) or {}
    entity_type = payload.get("entity_type") or ""
    entity_id = payload.get("entity_id") or ""
    summary = payload.get("summary") or ""
    if not entity_type or not entity_id:
        return error_response("entity_type and entity_id are required", status=400, code="bad_request")
    from flask import session

    from ..extensions import db
    from ..models import User

    actor_id = session.get("uid")
    actor = db.session.get(User, actor_id) if actor_id else None
    event = audit_service.record_audit(
        actor=actor,
        action=payload.get("action", "manual.note"),
        entity_type=entity_type,
        entity_id=entity_id,
        summary=summary,
    )
    return success_response({"audit": event.to_dict()}, status=201)
