"""Deterministic analytics engine.

Implements schema detection, summary statistics, filter expression
evaluation, calculated-column expressions, chart aggregation, CSV/PDF
export builders, and mock data-source adapters. No external services are
used; everything runs locally and deterministically.
"""
import ast
import csv
import io
import json
import operator
import re
import zlib

# ---------------------------------------------------------------------------
# File parsing and schema detection
# ---------------------------------------------------------------------------

ALLOWED_EXTENSIONS = {"csv", "json"}


def parse_upload(filename: str, content: bytes) -> dict:
    """Parse CSV/JSON file content into {columns, rows, format}.

    Raises ValueError with a deterministic message on invalid content.
    """
    ext = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""
    if ext not in ALLOWED_EXTENSIONS:
        raise ValueError(f"Unsupported file type '.{ext}'. Allowed: csv, json")
    if not content or len(content.strip()) == 0:
        raise ValueError("File is empty")
    if ext == "csv":
        text = content.decode("utf-8", errors="replace")
        reader = csv.reader(io.StringIO(text))
        rows = [row for row in reader if any(cell.strip() for cell in row)]
        if not rows:
            raise ValueError("CSV file contains no data rows")
        columns = [c.strip() for c in rows[0]]
        if len(columns) == 0 or any(not c for c in columns):
            raise ValueError("CSV header row must define column names")
        data_rows = [[_cell(v) for v in r] for r in rows[1:]]
        if len(data_rows) > 10000:
            raise ValueError("Dataset exceeds the 10,000 row limit")
        return {"columns": columns, "rows": data_rows, "format": "csv"}
    # JSON
    try:
        payload = json.loads(content.decode("utf-8"))
    except Exception as exc:  # noqa: BLE001
        raise ValueError(f"Invalid JSON: {exc}") from exc
    columns = []
    rows = []
    if isinstance(payload, list):
        if not payload or not isinstance(payload[0], dict):
            raise ValueError("JSON must be an array of objects or {columns, rows}")
        columns = list(payload[0].keys())
        rows = [[r.get(c) for c in columns] for r in payload]
    elif isinstance(payload, dict) and "columns" in payload and "rows" in payload:
        columns = [str(c) for c in payload["columns"]]
        rows = payload["rows"]
    else:
        raise ValueError("JSON must be an array of objects or {columns, rows}")
    if not columns:
        raise ValueError("JSON dataset defines no columns")
    if len(rows) > 10000:
        raise ValueError("Dataset exceeds the 10,000 row limit")
    return {"columns": columns, "rows": rows, "format": "json"}


def _cell(value):
    """Best-effort type conversion for CSV cells."""
    text = value.strip()
    if text == "":
        return None
    if re.fullmatch(r"[+-]?\d+", text):
        return int(text)
    if re.fullmatch(r"[+-]?\d*\.\d+([eE][+-]?\d+)?", text):
        return float(text)
    if text.lower() in ("true", "false"):
        return text.lower() == "true"
    return text


def _to_num(value):
    """Coerce a value to float/int when possible, else None."""
    if isinstance(value, bool):
        return None
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        text = value.strip()
        try:
            return float(text) if "." in text or "e" in text or "E" in text else int(text)
        except (TypeError, ValueError):
            return None
    return None


def detect_schema(columns, rows):
    """Map each column to a detected type: integer|number|boolean|date|text."""
    types = {}
    for idx, col in enumerate(columns):
        values = [r[idx] for r in rows if idx < len(r) and r[idx] is not None]
        types[col] = _column_type(values)
    return types


def _column_type(values):
    if not values:
        return "text"
    non_bool = [v for v in values if not isinstance(v, bool)]
    if len(non_bool) == len(values) and all(isinstance(v, bool) for v in non_bool):
        pass
    if all(isinstance(v, bool) for v in values):
        return "boolean"
    if all(isinstance(v, int) and not isinstance(v, bool) for v in values):
        return "integer"
    if all(isinstance(v, (int, float)) and not isinstance(v, bool) for v in values):
        return "number"
    if all(_is_date(v) for v in values):
        return "date"
    return "text"


_DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$")


def _is_date(value):
    if isinstance(value, str):
        return bool(_DATE_RE.match(value.strip()))
    return False


def summarize(columns, rows):
    """Summary statistics per column + global missing-value counts."""
    summary = []
    for idx, col in enumerate(columns):
        values = [r[idx] for r in rows if idx < len(r)]
        missing = sum(1 for v in values if v is None or v == "")
        distinct = len(set(v for v in values if v is not None))
        numbers = [_to_num(v) for v in values if _to_num(v) is not None]
        entry = {
            "column": col,
            "type": _column_type([v for v in values if v is not None]),
            "missing": missing,
            "distinct": distinct,
            "min": None,
            "max": None,
            "mean": None,
        }
        if numbers:
            entry["min"] = min(numbers)
            entry["max"] = max(numbers)
            entry["mean"] = round(sum(numbers) / len(numbers), 4)
        summary.append(entry)
    total_missing = sum(e["missing"] for e in summary)
    return {"columns": summary, "total_missing": total_missing}


# ---------------------------------------------------------------------------
# Filter expressions
# ---------------------------------------------------------------------------

FILTER_OPS = ("==", "!=", ">=", "<=", ">", "<", "contains")
_FILTER_PART = re.compile(
    r"^\s*([A-Za-z_][A-Za-z0-9_ ]*)\s*(==|!=|>=|<=|>|<|contains)\s*(.+?)\s*$"
)


def parse_filter(expression: str):
    """Split an AND-joined filter expression into condition dicts."""
    expression = (expression or "").strip()
    if not expression:
        raise ValueError("Filter expression is required")
    conditions = []
    for part in re.split(r"\s+AND\s+", expression, flags=re.IGNORECASE):
        match = _FILTER_PART.match(part)
        if not match:
            raise ValueError(
                f"Unsupported filter condition: '{part}'. "
                "Use '<column> <op> <value>' with AND between conditions."
            )
        column = match.group(1).strip()
        op = match.group(2)
        value = match.group(3).strip().strip('"').strip("'")
        conditions.append({"column": column, "op": op, "value": value})
    return conditions


def apply_filter(columns, rows, expression):
    """Return (conditions, filtered_rows, matched_count)."""
    conditions = parse_filter(expression)
    index = {c: i for i, c in enumerate(columns)}
    for cond in conditions:
        if cond["column"] not in index:
            raise ValueError(f"Unknown column: {cond['column']}")
    filtered = [r for r in rows if _row_matches(index, rows, r, conditions)]
    return conditions, filtered, len(filtered)


def _row_matches(index, rows, row, conditions):
    for cond in conditions:
        cell = row[index[cond["column"]]]
        a_num = _to_num(cell)
        b_num = _to_num(cond["value"])
        if a_num is not None and b_num is not None:
            left, right = a_num, b_num
            numeric = True
        else:
            left, right = _text(cell), _text(cond["value"])
            numeric = False
        op = cond["op"]
        if op == "==" and not _eq(left, right, numeric):
            return False
        if op == "!=" and not _ne(left, right, numeric):
            return False
        if op == ">" and not (left > right):
            return False
        if op == ">=" and not (left >= right):
            return False
        if op == "<" and not (left < right):
            return False
        if op == "<=" and not (left <= right):
            return False
        if op == "contains" and str(right).lower() not in str(left).lower():
            return False
    return True


def _text(value):
    return "" if value is None else str(value)


def _eq(left, right, numeric):
    if numeric:
        return left == right
    return str(left).lower() == str(right).lower()


def _ne(left, right, numeric):
    return not _eq(left, right, numeric)


# ---------------------------------------------------------------------------
# Calculated-column expressions (whitelisted AST evaluator)
# ---------------------------------------------------------------------------

_ARITHMETIC = {
    ast.Add: operator.add,
    ast.Sub: operator.sub,
    ast.Mult: operator.mul,
    ast.Div: operator.truediv,
    ast.FloorDiv: operator.floordiv,
    ast.Mod: operator.mod,
    ast.Pow: operator.pow,
}

_ALLOWED_FUNCS = {
    "abs": abs,
    "round": round,
    "upper": lambda v: str(v).upper(),
    "lower": lambda v: str(v).lower(),
    "len": lambda v: len(str(v)),
}


def validate_calc_expression(columns, rows, expression):
    """Validate an expression and return sample computed values."""
    index = {c: i for i, c in enumerate(columns)}
    try:
        tree = ast.parse((expression or "").strip(), mode="eval")
    except SyntaxError as exc:
        raise ValueError(f"Invalid expression: {exc.msg}") from exc
    _check_node(tree, index)
    sample = []
    for row in rows[:5]:
        sample.append(_eval_node(tree, index, row))
    return sample


def _check_node(node, index):
    """Reject unsupported AST nodes before evaluation."""
    if isinstance(node, ast.Expression):
        _check_node(node.body, index)
    elif isinstance(node, ast.Name):
        if node.id in index or node.id in ("True", "False", "None"):
            return
        raise ValueError(f"Unknown column: {node.id}")
    elif isinstance(node, (ast.Constant, ast.Num, ast.Str)):
        return
    elif isinstance(node, ast.BinOp):
        if type(node.op) not in _ARITHMETIC:
            raise ValueError(f"Unsupported operator: {type(node.op).__name__}")
        _check_node(node.left, index)
        _check_node(node.right, index)
    elif isinstance(node, ast.UnaryOp):
        if not isinstance(node.op, (ast.UAdd, ast.USub)):
            raise ValueError(f"Unsupported unary operator: {type(node.op).__name__}")
        _check_node(node.operand, index)
    elif isinstance(node, ast.Call):
        if not isinstance(node.func, ast.Name) or node.func.id not in _ALLOWED_FUNCS:
            raise ValueError("Only abs/round/upper/lower/len functions are allowed")
        for arg in node.args:
            _check_node(arg, index)
    else:
        raise ValueError(f"Unsupported expression element: {type(node).__name__}")


def _eval_node(node, index, row):
    if isinstance(node, ast.Expression):
        return _eval_node(node.body, index, row)
    if isinstance(node, ast.Constant):
        return node.value
    if isinstance(node, ast.Name):
        if node.id == "True":
            return True
        if node.id == "False":
            return False
        if node.id == "None":
            return None
        return row[index[node.id]]
    if isinstance(node, ast.BinOp):
        fn = _ARITHMETIC[type(node.op)]
        left = _eval_node(node.left, index, row)
        right = _eval_node(node.right, index, row)
        return fn(left, right)
    if isinstance(node, ast.UnaryOp):
        value = _eval_node(node.operand, index, row)
        if isinstance(node.op, ast.USub):
            return -value
        return +value
    if isinstance(node, ast.Call):
        fn = _ALLOWED_FUNCS[node.func.id]
        args = [_eval_node(a, index, row) for a in node.args]
        return fn(*args)
    raise ValueError("Unsupported expression element")


# ---------------------------------------------------------------------------
# Chart aggregation
# ---------------------------------------------------------------------------

AGGREGATIONS = ("count", "sum", "avg", "min", "max")


def aggregate_chart(columns, rows, x_col, y_col, agg):
    """Group rows by x_col and aggregate y_col; deterministic ordering."""
    if x_col not in columns:
        raise ValueError(f"Unknown X column: {x_col}")
    if y_col not in columns:
        raise ValueError(f"Unknown Y column: {y_col}")
    if agg not in AGGREGATIONS:
        raise ValueError(f"Unsupported aggregation: {agg}")
    xi, yi = columns.index(x_col), columns.index(y_col)
    groups = {}
    for row in rows:
        key = row[xi]
        value = _to_num(row[yi]) if agg != "count" else 1
        groups.setdefault(key, []).append(value if value is not None else 0)
    items = sorted(groups.items(), key=lambda kv: str(kv[0]))
    labels = [str(k) for k, _ in items]
    values = []
    for _, group in items:
        numbers = [v for v in group if v is not None]
        if agg == "count":
            values.append(len(group))
        elif agg == "sum":
            values.append(round(sum(numbers), 4))
        elif agg == "avg":
            values.append(round(sum(numbers) / len(numbers), 4) if numbers else 0)
        elif agg == "min":
            values.append(min(numbers) if numbers else 0)
        elif agg == "max":
            values.append(max(numbers) if numbers else 0)
    return {"labels": labels, "values": values, "agg": agg}


# ---------------------------------------------------------------------------
# Export builders (deterministic local adapters)
# ---------------------------------------------------------------------------

def build_csv(columns, rows):
    """Return CSV bytes for columns/rows."""
    buffer = io.StringIO()
    writer = csv.writer(buffer)
    writer.writerow(columns)
    for row in rows:
        writer.writerow([_to_cell(v) for v in row])
    return buffer.getvalue().encode("utf-8")


def _to_cell(value):
    if value is None:
        return ""
    if isinstance(value, bool):
        return "true" if value else "false"
    return value


def build_pdf(title, lines):
    """Return a minimal, valid single-page PDF with text lines."""
    escaped = []
    for line in lines[:42]:
        safe = "".join(ch if 32 <= ord(ch) < 127 else "?" for ch in str(line))
        escaped.append(safe.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)"))
    content = []
    y = 780
    for line in escaped:
        content.append(f"BT /F1 11 Tf 40 {y} Td ({line}) Tj ET")
        y -= 16
        if y < 44:
            break
    stream = "\n".join(content)
    objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        "/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        f"<< /Length {len(stream.encode('latin-1'))} >>\nstream\n{stream}\nendstream",
    ]
    body = ["%PDF-1.4"]
    offsets = []
    position = len(body[0]) + 1  # bytes after "%PDF-1.4\n"
    for number, obj in enumerate(objects, start=1):
        offsets.append(position)
        chunk = f"{number} 0 obj\n{obj}\nendobj\n"
        body.append(chunk)
        position += len(chunk.encode("latin-1"))
    xref_offset = position
    xref = f"xref\n0 {len(objects) + 1}\n0000000000 65535 f \n"
    for off in offsets:
        xref += f"{off:010d} 00000 n \n"
    trailer = (
        f"trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\n"
        f"startxref\n{xref_offset}\n%%EOF"
    )
    return (
        "%PDF-1.4\n"
        + "".join(body[1:])
        + xref
        + trailer
    ).encode("latin-1")


# ---------------------------------------------------------------------------
# Mock data-source adapters (DATA-10)
# ---------------------------------------------------------------------------

def mock_source_data(kind, name, seed):
    """Deterministic mock rows for a mockdb/mockapi data source."""
    rng_seed = zlib.crc32(f"{kind}:{name}:{seed}".encode("utf-8"))
    if kind == "mockdb":
        columns = ["id", "account", "balance", "status"]
        rows = [
            [
                i,
                f"acct-{i:03d}",
                round(1000 + (rng_seed * 31 + i * 137) % 9000, 2),
                "active" if i % 3 else "pending",
            ]
            for i in range(1, 11)
        ]
    elif kind == "mockapi":
        symbols = ["EUR", "GBP", "JPY", "CAD", "AUD"]
        columns = ["symbol", "price", "volume", "date"]
        rows = [
            [
                symbols[i % len(symbols)],
                round(1.0 + (rng_seed * 7 + i * 11) % 2000 / 1000, 4),
                100 + (rng_seed + i * 53) % 900,
                f"2026-{i % 12 + 1:02d}-{i % 28 + 1:02d}",
            ]
            for i in range(1, 11)
        ]
    else:
        raise ValueError(f"Unsupported data source kind: {kind}")
    return {"columns": columns, "rows": rows}
