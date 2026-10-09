"""Audit and lineage service: persist audit events and lineage edges."""
from __future__ import annotations

from typing import Any

from ..extensions import db
from ..models import AuditEvent, LineageRecord, User


def record_audit(
    *,
    actor: User | None,
    action: str,
    entity_type: str,
    entity_id: str | int,
    summary: str = "",
) -> AuditEvent:
    actor_id = actor.id if actor else None
    username = actor.username if actor else "system"
    event = AuditEvent(
        actor_id=actor_id,
        actor_username=username,
        action=action,
        entity_type=entity_type,
        entity_id=str(entity_id),
        summary=summary,
    )
    db.session.add(event)
    db.session.commit()
    return event


def record_lineage(
    *,
    parent_type: str,
    parent_id: str | int,
    child_type: str,
    child_id: str | int,
    transformation: str = "",
) -> LineageRecord:
    record = LineageRecord(
        parent_type=parent_type,
        parent_id=str(parent_id),
        child_type=child_type,
        child_id=str(child_id),
        transformation=transformation,
    )
    db.session.add(record)
    db.session.commit()
    return record


def list_audit(limit: int = 200) -> list[dict[str, Any]]:
    events = (
        db.session.query(AuditEvent)
        .order_by(AuditEvent.created_at.desc(), AuditEvent.id.desc())
        .limit(limit)
        .all()
    )
    return [event.to_dict() for event in events]


def list_lineage(dataset_id: int | None = None) -> list[dict[str, Any]]:
    query = db.session.query(LineageRecord).order_by(LineageRecord.created_at.asc())
    if dataset_id is not None:
        dataset_str = str(dataset_id)
        query = query.filter(
            (LineageRecord.parent_id == dataset_str)
            | (LineageRecord.child_id == dataset_str)
        )
    return [record.to_dict() for record in query.all()]
