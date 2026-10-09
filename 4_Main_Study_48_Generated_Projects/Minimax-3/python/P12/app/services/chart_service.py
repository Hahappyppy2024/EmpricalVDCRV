"""Chart builder: generate aggregates and chart-ready payloads."""
from __future__ import annotations

import json
from collections import defaultdict
from typing import Any

from .dataset_service import load_dataset_rows
from .expression_engine import apply_filter
from ..models import ChartSpec, Dataset
from ..utils import ApiError


ALLOWED_CHART_TYPES = {"bar", "line", "pie", "scatter"}


def _to_number(value: Any) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def _coerce_group(value: Any) -> str:
    if value is None or value == "":
        return "(empty)"
    return str(value)


def build_chart_payload(
    dataset: Dataset,
    chart_type: str,
    config: dict[str, Any],
    filter_expression: str = "",
    limit: int = 1000,
) -> dict[str, Any]:
    if chart_type not in ALLOWED_CHART_TYPES:
        raise ApiError(
            f"Unsupported chart type '{chart_type}'. Allowed: {sorted(ALLOWED_CHART_TYPES)}",
            status=400,
            code="bad_request",
        )
    schema = json.loads(dataset.schema_json)
    schema_columns = {col["name"] for col in schema}
    x_field = config.get("x")
    y_field = config.get("y")
    aggregate = config.get("aggregate", "sum" if chart_type != "scatter" else "none")
    if not x_field or x_field not in schema_columns:
        raise ApiError("Chart config requires a valid 'x' column", status=400, code="bad_request")
    if chart_type != "pie" and (not y_field or y_field not in schema_columns):
        raise ApiError("Chart config requires a valid 'y' column", status=400, code="bad_request")

    rows = load_dataset_rows(dataset, max_rows=limit)
    if filter_expression:
        rows = apply_filter(rows, filter_expression, schema_columns)

    if chart_type == "scatter":
        points = []
        for row in rows:
            xv = _to_number(row.get(x_field))
            yv = _to_number(row.get(y_field)) if y_field else None
            if xv is None or yv is None:
                continue
            points.append({"x": xv, "y": yv})
        return {
            "chart_type": chart_type,
            "x": x_field,
            "y": y_field,
            "points": points,
            "row_count": len(rows),
        }

    buckets: dict[str, list[float]] = defaultdict(list)
    for row in rows:
        group_key = _coerce_group(row.get(x_field))
        numeric = _to_number(row.get(y_field))
        if numeric is None:
            continue
        buckets[group_key].append(numeric)

    series: list[dict[str, Any]] = []
    for key, values in buckets.items():
        if aggregate == "sum":
            value = sum(values)
        elif aggregate == "avg":
            value = sum(values) / len(values) if values else 0
        elif aggregate == "count":
            value = len(values)
        elif aggregate == "min":
            value = min(values)
        elif aggregate == "max":
            value = max(values)
        else:
            value = sum(values)
        series.append({"label": key, "value": value})

    series.sort(key=lambda item: item["label"])
    return {
        "chart_type": chart_type,
        "x": x_field,
        "y": y_field,
        "aggregate": aggregate,
        "series": series,
        "row_count": len(rows),
    }


def chart_to_dict(chart: ChartSpec) -> dict[str, Any]:
    payload = chart.to_dict()
    try:
        config = json.loads(chart.config_json)
    except json.JSONDecodeError:
        config = {}
    payload["computed"] = build_chart_payload(
        chart.dataset, chart.chart_type, config, chart.filter_expression
    )
    return payload
