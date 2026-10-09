"""Dataset parsing, schema detection, and CRUD services."""
from __future__ import annotations

import csv
import json
import os
import uuid
from pathlib import Path
from typing import Any

from flask import current_app

from ..extensions import db
from ..models import Dataset, User
from ..utils import ApiError


ALLOWED_EXTENSIONS = {".csv", ".json"}
MAX_UPLOAD_BYTES = 25 * 1024 * 1024


def _save_file(file_storage, owner_id: int) -> tuple[str, str]:
    filename = file_storage.filename or "dataset"
    ext = Path(filename).suffix.lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise ApiError(
            f"Unsupported file extension '{ext}'. Allowed: {sorted(ALLOWED_EXTENSIONS)}",
            status=400,
            code="bad_request",
        )
    upload_dir = Path(current_app.config["UPLOAD_FOLDER"])
    upload_dir.mkdir(parents=True, exist_ok=True)
    storage_name = f"u{owner_id}_{uuid.uuid4().hex}{ext}"
    destination = upload_dir / storage_name
    file_storage.save(destination)
    size = destination.stat().st_size
    if size == 0:
        destination.unlink(missing_ok=True)
        raise ApiError("Uploaded file is empty", status=400, code="bad_request")
    if size > MAX_UPLOAD_BYTES:
        destination.unlink(missing_ok=True)
        raise ApiError("Uploaded file is too large", status=400, code="bad_request")
    return destination.as_posix(), ext.lstrip(".")


def _detect_schema(rows: list[dict[str, Any]]) -> list[dict[str, str]]:
    if not rows:
        return []
    columns: dict[str, str] = {}
    for row in rows:
        for key, value in row.items():
            if key in columns:
                continue
            columns[key] = _infer_type(value)
    return [{"name": name, "type": dtype} for name, dtype in columns.items()]


def _infer_type(value: Any) -> str:
    if value is None or value == "":
        return "string"
    if isinstance(value, bool):
        return "boolean"
    if isinstance(value, (int, float)):
        return "number"
    if isinstance(value, str):
        stripped = value.strip()
        if not stripped:
            return "string"
        lowered = stripped.lower()
        if lowered in {"true", "false"}:
            return "boolean"
        try:
            int(stripped)
            return "integer"
        except ValueError:
            pass
        try:
            float(stripped)
            return "number"
        except ValueError:
            pass
        return "string"
    return "string"


def _coerce_csv_value(value: str | None) -> Any:
    """Best-effort coercion of a CSV cell value to int/float/bool when possible."""
    if value is None:
        return None
    stripped = value.strip()
    if not stripped:
        return None
    lowered = stripped.lower()
    if lowered in {"true", "false"}:
        return lowered == "true"
    try:
        return int(stripped)
    except ValueError:
        pass
    try:
        return float(stripped)
    except ValueError:
        return stripped


def _parse_csv(path: str) -> list[dict[str, Any]]:
    with open(path, "r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        rows: list[dict[str, Any]] = []
        for raw_row in reader:
            normalized: dict[str, Any] = {}
            for key, value in raw_row.items():
                if key is None:
                    continue
                normalized[key] = _coerce_csv_value(value)
            rows.append(normalized)
    return rows


def _parse_json(path: str) -> list[dict[str, Any]]:
    with open(path, "r", encoding="utf-8") as handle:
        data = json.load(handle)
    if isinstance(data, list):
        if not all(isinstance(item, dict) for item in data):
            raise ApiError("JSON must be an array of objects", status=400, code="bad_request")
        return data
    if isinstance(data, dict):
        if "data" in data and isinstance(data["data"], list):
            return data["data"]
        return [data]
    raise ApiError("JSON must be an object or array of objects", status=400, code="bad_request")


def load_dataset_rows(dataset: Dataset, *, max_rows: int | None = None) -> list[dict[str, Any]]:
    if dataset.source_format == "csv":
        rows = _parse_csv(dataset.file_path)
    elif dataset.source_format == "json":
        rows = _parse_json(dataset.file_path)
    else:
        raise ApiError("Unsupported dataset format", status=400, code="bad_request")
    if max_rows is not None:
        return rows[:max_rows]
    return rows


def persist_upload(
    *,
    owner: User,
    name: str,
    description: str,
    visibility: str,
    tags: list[str],
    file_storage,
) -> Dataset:
    file_path, ext = _save_file(file_storage, owner.id)

    if ext == "csv":
        rows = _parse_csv(file_path)
    else:
        rows = _parse_json(file_path)

    schema = _detect_schema(rows)
    schema_json = json.dumps(schema)
    tag_string = ",".join(sorted({t.strip().lower() for t in tags if t.strip()}))

    dataset = Dataset(
        name=name,
        description=description,
        owner_id=owner.id,
        visibility=visibility,
        source_format=ext,
        file_path=file_path,
        row_count=len(rows),
        column_count=len(schema),
        schema_json=schema_json,
        tags_csv=tag_string,
    )
    db.session.add(dataset)
    db.session.flush()
    max_owned = current_app.config["MAX_DATASETS_PER_USER"]
    owned = db.session.query(Dataset).filter_by(owner_id=owner.id).count()
    if owned > max_owned:
        db.session.rollback()
        try:
            os.remove(file_path)
        except OSError:
            pass
        raise ApiError(
            f"Dataset limit reached ({max_owned}). Delete an existing dataset or contact an admin.",
            status=400,
            code="bad_request",
        )
    db.session.commit()
    return dataset


def can_view(dataset: Dataset, user: User) -> bool:
    if user.role == "admin":
        return True
    if dataset.owner_id == user.id:
        return True
    if dataset.visibility == "public":
        return True
    if dataset.visibility == "team":
        return True
    return False


def can_modify(dataset: Dataset, user: User) -> bool:
    if user.role == "admin":
        return True
    if dataset.owner_id != user.id:
        return False
    return True


def get_dataset_or_404(dataset_id: int) -> Dataset:
    dataset = db.session.get(Dataset, dataset_id)
    if dataset is None:
        raise ApiError("Dataset not found", status=404, code="not_found")
    return dataset
