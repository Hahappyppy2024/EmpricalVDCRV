"""DATA-03 — Dataset catalog: list, search, tag, describe datasets."""
from __future__ import annotations

from flask import Blueprint, request

from ..extensions import db
from ..models import Dataset
from ..services import audit_service
from ..services.dataset_service import can_modify, can_view, get_dataset_or_404
from ..utils import (
    error_response,
    get_current_user,
    require_auth,
    success_response,
    ROLE_VIEWER,
    ROLE_ANALYST,
    ROLE_ADMIN,
    ALL_ROLES,
)

bp = Blueprint("dataset_catalog", __name__)


def _visible_datasets(user) -> list[Dataset]:
    query = db.session.query(Dataset)
    if user.role != "admin":
        query = query.filter(
            (Dataset.owner_id == user.id) | (Dataset.visibility != "private")
        )
    return query.order_by(Dataset.updated_at.desc()).all()


@bp.get("/api/data/dataset_catalog")
@require_auth(roles=ALL_ROLES)
def list_catalog():
    user = get_current_user()
    search = (request.args.get("q") or "").strip().lower()
    tag_filter = (request.args.get("tag") or "").strip().lower()

    datasets = _visible_datasets(user)
    results = []
    for dataset in datasets:
        if search:
            haystack = " ".join(
                [dataset.name.lower(), dataset.description.lower(), dataset.tags_csv.lower()]
            )
            if search not in haystack:
                continue
        if tag_filter and tag_filter not in [t.lower() for t in dataset.tags_csv.split(",") if t]:
            continue
        results.append(dataset.to_summary())

    return success_response({"datasets": results, "count": len(results)})


@bp.post("/api/data/dataset_catalog")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN})
def update_catalog_metadata():
    """Apply tag / description patches to a dataset owned by the actor."""
    user = get_current_user()
    payload = request.get_json(silent=True) or {}
    dataset_id = payload.get("dataset_id")
    if not isinstance(dataset_id, int):
        return error_response("dataset_id is required", status=400, code="bad_request")
    dataset = get_dataset_or_404(dataset_id)
    if not can_modify(dataset, user):
        return error_response("Insufficient privileges", status=403, code="forbidden")

    if "tags" in payload:
        tags = payload.get("tags") or []
        if not isinstance(tags, list):
            return error_response("tags must be a list", status=400, code="bad_request")
        dataset.tags_csv = ",".join(
            sorted({str(t).strip().lower() for t in tags if str(t).strip()})
        )
    if "description" in payload:
        dataset.description = (payload.get("description") or "").strip()
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="dataset.catalog.update",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} updated catalog metadata",
    )
    return success_response({"dataset": dataset.to_summary()})


@bp.patch("/api/data/dataset_catalog/<int:dataset_id>")
@require_auth(roles={ROLE_ANALYST, ROLE_ADMIN, ROLE_VIEWER})
def patch_catalog(dataset_id: int):
    user = get_current_user()
    dataset = get_dataset_or_404(dataset_id)
    if not can_view(dataset, user):
        return error_response("Dataset not visible", status=404, code="not_found")
    if user.role == ROLE_VIEWER and not can_modify(dataset, user):
        return error_response(
            "Viewers cannot modify catalog metadata",
            status=403,
            code="forbidden",
        )
    payload = request.get_json(silent=True) or {}
    if "description" in payload:
        dataset.description = (payload.get("description") or "").strip()
    if "tags" in payload:
        tags = payload.get("tags") or []
        if not isinstance(tags, list):
            return error_response("tags must be a list", status=400, code="bad_request")
        dataset.tags_csv = ",".join(
            sorted({str(t).strip().lower() for t in tags if str(t).strip()})
        )
    db.session.commit()
    audit_service.record_audit(
        actor=user,
        action="dataset.catalog.patch",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} patched catalog record",
    )
    return success_response({"dataset": dataset.to_summary()})
