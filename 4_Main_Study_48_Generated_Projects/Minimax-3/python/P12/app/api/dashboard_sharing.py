"""DATA-08 — Dashboard sharing."""
from __future__ import annotations

import secrets
from datetime import datetime, timedelta

from flask import Blueprint, request

from ..extensions import db
from ..models import DashboardShare
from ..services import audit_service
from ..services.dataset_service import can_modify, can_view, get_dataset_or_404
from ..utils import (
    ApiError,
    error_response,
    get_current_user,
    is_bool,
    is_choice,
    is_int,
    is_safe_string,
    parse_iso,
    require_auth,
    success_response,
    ALL_ROLES,
    ROLE_ADMIN,
)


bp = Blueprint("dashboard_sharing", __name__)


@bp.get("/api/data/dashboard_sharing")
@require_auth(roles=ALL_ROLES)
def list_shares():
    user = get_current_user()
    dataset_id = request.args.get("dataset_id")
    query = db.session.query(DashboardShare)
    if dataset_id:
        try:
            dataset_id_int = is_int(dataset_id, field="dataset_id", minimum=1)
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        query = query.filter_by(dataset_id=dataset_id_int)
    shares = query.order_by(DashboardShare.created_at.desc()).all()
    if user.role != ROLE_ADMIN:
        shares = [s for s in shares if s.owner_id == user.id]
    return success_response({"shares": [s.to_dict() for s in shares]})


@bp.post("/api/data/dashboard_sharing")
@require_auth(roles=ALL_ROLES)
def create_share():
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    try:
        dataset_id = is_int(payload.get("dataset_id", 0), field="dataset_id", minimum=1)
        audience = is_choice(
            payload.get("audience", "team"),
            choices={"team", "link", "public"},
            field="audience",
        )
        permission = is_choice(
            payload.get("permission", "view"),
            choices={"view", "comment"},
            field="permission",
        )
        description = (payload.get("description") or "").strip()
        ttl_hours = payload.get("ttl_hours")
        if ttl_hours not in (None, ""):
            ttl_hours = is_int(ttl_hours, field="ttl_hours", minimum=1, maximum=24 * 30)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")

    expires_at = None
    if ttl_hours:
        expires_at = datetime.utcnow() + timedelta(hours=ttl_hours)

    record = DashboardShare(
        dataset_id=dataset.id,
        owner_id=user.id,
        link_token=secrets.token_urlsafe(24),
        audience=audience,
        permission=permission,
        description=description,
        expires_at=expires_at,
    )
    db.session.add(record)
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="share.create",
        entity_type="share",
        entity_id=record.id,
        summary=f"{user.username} shared dataset {dataset.name}",
    )
    audit_service.record_lineage(
        parent_type="dataset",
        parent_id=dataset.id,
        child_type="share",
        child_id=record.id,
        transformation="share",
    )
    return success_response({"share": record.to_dict()}, status=201)


@bp.patch("/api/data/dashboard_sharing/<int:share_id>")
@require_auth(roles=ALL_ROLES)
def update_share(share_id: int):
    user = get_current_user()
    record = db.session.get(DashboardShare, share_id)
    if record is None:
        return error_response("Share not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != ROLE_ADMIN:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = request.get_json(silent=True) or {}
    if "audience" in payload:
        record.audience = is_choice(
            payload.get("audience", record.audience),
            choices={"team", "link", "public"},
            field="audience",
        )
    if "permission" in payload:
        record.permission = is_choice(
            payload.get("permission", record.permission),
            choices={"view", "comment"},
            field="permission",
        )
    if "description" in payload:
        record.description = (payload.get("description") or "").strip()
    if "revoked" in payload:
        record.revoked = is_bool(payload.get("revoked", False), field="revoked")
    if "expires_at" in payload:
        record.expires_at = parse_iso(payload.get("expires_at"))
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="share.update",
        entity_type="share",
        entity_id=record.id,
        summary=f"{user.username} updated share",
    )
    return success_response({"share": record.to_dict()})


@bp.delete("/api/data/dashboard_sharing/<int:share_id>")
@require_auth(roles=ALL_ROLES)
def revoke_share(share_id: int):
    user = get_current_user()
    record = db.session.get(DashboardShare, share_id)
    if record is None:
        return error_response("Share not found", status=404, code="not_found")
    if record.owner_id != user.id and user.role != ROLE_ADMIN:
        return error_response("Insufficient privileges", status=403, code="forbidden")
    record.revoked = True
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="share.revoke",
        entity_type="share",
        entity_id=share_id,
        summary=f"{user.username} revoked share",
    )
    return success_response({"revoked": share_id})


@bp.get("/api/data/dashboard_sharing/public/<string:token>")
def public_share(token: str):
    """Unauthenticated link-based access for share tokens (audience=link)."""
    record = (
        db.session.query(DashboardShare)
        .filter_by(link_token=token)
        .first()
    )
    if record is None or record.revoked:
        return error_response("Share not found", status=404, code="not_found")
    if record.audience not in {"link", "public"}:
        return error_response("Share is not link-accessible", status=403, code="forbidden")
    if record.expires_at is not None and record.expires_at < datetime.utcnow():
        return error_response("Share has expired", status=410, code="gone")
    dataset = record.dataset
    if dataset.visibility == "private":
        return error_response("Share is not available", status=403, code="forbidden")
    audit_service.record_audit(
        actor=None,
        action="share.access",
        entity_type="share",
        entity_id=record.id,
        summary=f"Public access via share token ({token[:8]}…)",
    )
    return success_response(
        {
            "share": record.to_dict(),
            "dataset": dataset.to_summary(),
        }
    )
