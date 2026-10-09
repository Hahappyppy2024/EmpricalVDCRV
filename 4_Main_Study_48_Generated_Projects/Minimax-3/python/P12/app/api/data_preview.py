"""DATA-04 — Data preview."""
from __future__ import annotations

import json

from flask import Blueprint, request

from ..services import audit_service
from ..services.dataset_service import (
    can_view,
    get_dataset_or_404,
    load_dataset_rows,
)
from ..services.expression_engine import compute_calculated_columns, summarize
from ..extensions import db
from ..models import CalculatedColumn
from ..utils import (
    error_response,
    get_current_user,
    is_int,
    require_auth,
    success_response,
    ALL_ROLES,
)


bp = Blueprint("data_preview", __name__)


@bp.get("/api/data/data_preview")
@require_auth(roles=ALL_ROLES)
def get_preview():
    user = get_current_user()
    try:
        dataset_id = is_int(request.args.get("dataset_id", 0), field="dataset_id", minimum=1)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")
    try:
        offset = is_int(request.args.get("offset", 0), field="offset", minimum=0, maximum=100000)
        limit = is_int(request.args.get("limit", 50), field="limit", minimum=1, maximum=500)
    except Exception as exc:  # noqa: BLE001
        return error_response(str(exc), status=400, code="bad_request")

    dataset = get_dataset_or_404(dataset_id)
    if not can_view(dataset, user):
        return error_response("Dataset not visible", status=404, code="not_found")

    rows = load_dataset_rows(dataset)
    schema = json.loads(dataset.schema_json)
    schema_columns = {col["name"] for col in schema}
    calculated = (
        db.session.query(CalculatedColumn).filter_by(dataset_id=dataset.id).all()
    )
    if calculated:
        rows = compute_calculated_columns(
            rows,
            [(c.name, c.expression) for c in calculated],
            schema_columns | {c.name for c in calculated},
        )
    summary = summarize(rows, schema + [
        {"name": c.name, "type": c.return_type} for c in calculated
    ])
    page_rows = rows[offset : offset + limit]
    audit_service.record_audit(
        actor=user,
        action="dataset.preview",
        entity_type="dataset",
        entity_id=dataset.id,
        summary=f"{user.username} viewed dataset preview",
    )
    return success_response(
        {
            "dataset": dataset.to_summary(),
            "rows": page_rows,
            "total_rows": len(rows),
            "summary": summary,
            "offset": offset,
            "limit": limit,
        }
    )


@bp.post("/api/data/data_preview")
@require_auth(roles=ALL_ROLES)
def post_preview():
    return get_preview()
