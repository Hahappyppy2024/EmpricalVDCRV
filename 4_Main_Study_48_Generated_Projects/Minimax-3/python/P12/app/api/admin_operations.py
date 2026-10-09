"""DATA-12 — Admin operations."""
from __future__ import annotations

from datetime import datetime, timedelta

from flask import Blueprint, current_app, request

from ..extensions import db
from ..models import AdminSettings, AuditEvent, Dataset, LineageRecord, User
from ..services.auth_service import hash_password, revoke_user_sessions
from ..utils import (
    error_response,
    get_current_user,
    is_bool,
    is_int,
    is_safe_string,
    require_auth,
    success_response,
    ROLE_ADMIN,
)

bp = Blueprint("admin_operations", __name__)


def _set_setting(key: str, value: str, actor_id: int) -> AdminSettings:
    record = db.session.query(AdminSettings).filter_by(key=key).first()
    if record is None:
        record = AdminSettings(key=key, value=value, updated_by=actor_id)
        db.session.add(record)
    else:
        record.value = value
        record.updated_by = actor_id
    db.session.commit()
    return record


@bp.get("/api/data/admin_operations")
@require_auth(roles={ROLE_ADMIN})
def get_admin_overview():
    user_count = db.session.query(User).count()
    session_count = db.session.query(User).filter(User.last_login_at.isnot(None)).count()
    dataset_count = db.session.query(Dataset).count()
    audit_count = db.session.query(AuditEvent).count()
    lineage_count = db.session.query(LineageRecord).count()
    settings = {
        record.key: record.value
        for record in db.session.query(AdminSettings).all()
    }
    return success_response(
        {
            "stats": {
                "user_count": user_count,
                "active_sessions": session_count,
                "dataset_count": dataset_count,
                "audit_count": audit_count,
                "lineage_count": lineage_count,
            },
            "settings": settings,
            "limits": {
                "dataset_retention_days": current_app.config["DATASET_RETENTION_DAYS"],
                "max_datasets_per_user": current_app.config["MAX_DATASETS_PER_USER"],
            },
        }
    )


@bp.post("/api/data/admin_operations")
@require_auth(roles={ROLE_ADMIN})
def admin_action():
    actor = get_current_user()
    payload = request.get_json(silent=True) or {}
    action = payload.get("action") or ""

    if action == "settings.update":
        try:
            key = is_safe_string(payload.get("key", ""), max_length=64, field="key")
            value = str(payload.get("value", ""))
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        record = _set_setting(key, value, actor.id)
        from ..services import audit_service

        audit_service.record_audit(
            actor=actor,
            action="admin.settings.update",
            entity_type="setting",
            entity_id=key,
            summary=f"{actor.username} set {key}={value}",
        )
        return success_response({"setting": record.to_dict()})

    if action == "user.role.update":
        try:
            user_id = is_int(payload.get("user_id", 0), field="user_id", minimum=1)
            role = is_safe_string(payload.get("role", "viewer"), max_length=16, field="role")
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        if role not in {"analyst", "viewer", "admin"}:
            return error_response("Invalid role", status=400, code="bad_request")
        target = db.session.get(User, user_id)
        if target is None:
            return error_response("User not found", status=404, code="not_found")
        target.role = role
        db.session.commit()
        from ..services import audit_service

        audit_service.record_audit(
            actor=actor,
            action="admin.user.role",
            entity_type="user",
            entity_id=target.id,
            summary=f"{actor.username} set role to {role}",
        )
        return success_response({"user": target.to_dict()})

    if action == "user.password.reset":
        try:
            user_id = is_int(payload.get("user_id", 0), field="user_id", minimum=1)
            new_password = is_safe_string(
                payload.get("new_password", ""), max_length=128, field="new_password"
            )
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        if len(new_password) < 6:
            return error_response(
                "Password must be at least 6 characters",
                status=400,
                code="bad_request",
            )
        target = db.session.get(User, user_id)
        if target is None:
            return error_response("User not found", status=404, code="not_found")
        target.password_hash = hash_password(new_password)
        db.session.commit()
        revoke_user_sessions(target.id)
        from ..services import audit_service

        audit_service.record_audit(
            actor=actor,
            action="admin.user.reset",
            entity_type="user",
            entity_id=target.id,
            summary=f"{actor.username} reset password",
        )
        return success_response({"user": target.to_dict()})

    if action == "user.deactivate":
        try:
            user_id = is_int(payload.get("user_id", 0), field="user_id", minimum=1)
            deactivate = is_bool(payload.get("active", False), field="active") is False
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        target = db.session.get(User, user_id)
        if target is None:
            return error_response("User not found", status=404, code="not_found")
        target.is_active = not deactivate
        db.session.commit()
        revoke_user_sessions(target.id) if deactivate else None
        from ..services import audit_service

        audit_service.record_audit(
            actor=actor,
            action="admin.user.deactivate" if deactivate else "admin.user.activate",
            entity_type="user",
            entity_id=target.id,
            summary=f"{actor.username} changed active to {target.is_active}",
        )
        return success_response({"user": target.to_dict()})

    if action == "datasets.purge":
        try:
            older_than_days = is_int(
                payload.get("older_than_days", current_app.config["DATASET_RETENTION_DAYS"]),
                field="older_than_days",
                minimum=1,
                maximum=3650,
            )
        except Exception as exc:  # noqa: BLE001
            return error_response(str(exc), status=400, code="bad_request")
        cutoff = datetime.utcnow() - timedelta(days=older_than_days)
        candidates = (
            db.session.query(Dataset).filter(Dataset.created_at < cutoff).all()
        )
        purged = 0
        for dataset in candidates:
            db.session.delete(dataset)
            purged += 1
        db.session.commit()
        from ..services import audit_service

        audit_service.record_audit(
            actor=actor,
            action="admin.datasets.purge",
            entity_type="dataset",
            entity_id="bulk",
            summary=f"{actor.username} purged {purged} older-than-{older_than_days}d datasets",
        )
        return success_response({"purged": purged, "older_than_days": older_than_days})

    return error_response(f"Unknown action '{action}'", status=400, code="bad_request")


@bp.patch("/api/data/admin_operations/<int:setting_id>")
@require_auth(roles={ROLE_ADMIN})
def patch_setting(setting_id: int):
    actor = get_current_user()
    record = db.session.get(AdminSettings, setting_id)
    if record is None:
        return error_response("Setting not found", status=404, code="not_found")
    payload = request.get_json(silent=True) or {}
    if "value" in payload:
        record.value = str(payload.get("value", ""))
        record.updated_by = actor.id
    db.session.commit()
    from ..services import audit_service

    audit_service.record_audit(
        actor=actor,
        action="admin.settings.patch",
        entity_type="setting",
        entity_id=record.key,
        summary=f"{actor.username} patched {record.key}",
    )
    return success_response({"setting": record.to_dict()})


@bp.get("/api/data/admin_operations/users")
@require_auth(roles={ROLE_ADMIN})
def list_users():
    users = db.session.query(User).order_by(User.created_at.asc()).all()
    return success_response({"users": [u.to_dict() for u in users]})
