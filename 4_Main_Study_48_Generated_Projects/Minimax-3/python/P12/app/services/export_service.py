"""Export service: produce CSV, JSON, and HTML/PDF-ready files for a dataset."""
from __future__ import annotations

import csv
import io
import json
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any

from flask import current_app

from .dataset_service import load_dataset_rows
from .expression_engine import apply_filter
from ..models import Dataset, ExportRecord, User


def _safe_filename(name: str) -> str:
    cleaned = "".join(c if c.isalnum() or c in ("-", "_") else "_" for c in name.strip())
    return cleaned[:64] or "dataset"


def export_dataset(
    *,
    owner: User,
    dataset: Dataset,
    export_format: str,
    filter_expression: str = "",
) -> ExportRecord:
    if export_format not in {"csv", "json", "pdf"}:
        raise ValueError("Unsupported export format")
    schema = json.loads(dataset.schema_json)
    columns = {col["name"] for col in schema}
    rows = load_dataset_rows(dataset)
    if filter_expression:
        rows = apply_filter(rows, filter_expression, columns)

    export_dir = Path(current_app.config["EXPORT_FOLDER"])
    export_dir.mkdir(parents=True, exist_ok=True)

    timestamp = datetime.utcnow().strftime("%Y%m%d_%H%M%S")
    base = f"{_safe_filename(dataset.name)}_{timestamp}_{uuid.uuid4().hex[:8]}"
    if export_format == "csv":
        path = export_dir / f"{base}.csv"
        with path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.writer(handle)
            if rows:
                writer.writerow(list(rows[0].keys()))
            for row in rows:
                writer.writerow([row.get(k, "") for k in rows[0].keys()])
    elif export_format == "json":
        path = export_dir / f"{base}.json"
        with path.open("w", encoding="utf-8") as handle:
            json.dump({"dataset_id": dataset.id, "rows": rows}, handle, indent=2, default=str)
    else:
        path = export_dir / f"{base}.html"
        with path.open("w", encoding="utf-8") as handle:
            handle.write(_render_html(dataset, rows, schema))

    record = ExportRecord(
        dataset_id=dataset.id,
        owner_id=owner.id,
        export_format=export_format,
        file_path=path.as_posix(),
        row_count=len(rows),
        filter_expression=filter_expression,
        status="ready",
    )
    from ..extensions import db

    db.session.add(record)
    db.session.commit()
    return record


def _render_html(dataset: Dataset, rows: list[dict[str, Any]], schema: list[dict[str, str]]) -> str:
    buffer = io.StringIO()
    buffer.write("<!doctype html><html><head><meta charset='utf-8'>")
    buffer.write(f"<title>{dataset.name} export</title>")
    buffer.write("<style>body{font-family:Arial,sans-serif;}table{border-collapse:collapse;width:100%;}td,th{border:1px solid #ccc;padding:4px 6px;}th{background:#f3f4f6;}</style>")
    buffer.write("</head><body>")
    buffer.write(f"<h1>{dataset.name}</h1>")
    buffer.write(f"<p>Exported {len(rows)} rows &middot; {len(schema)} columns</p>")
    if rows:
        buffer.write("<table><thead><tr>")
        for col in schema:
            buffer.write(f"<th>{col['name']}</th>")
        buffer.write("</tr></thead><tbody>")
        for row in rows[:500]:
            buffer.write("<tr>")
            for col in schema:
                buffer.write(f"<td>{row.get(col['name'], '')}</td>")
            buffer.write("</tr>")
        buffer.write("</tbody></table>")
    buffer.write("</body></html>")
    return buffer.getvalue()
