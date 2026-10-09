"""Safe expression evaluator for filter builder and calculated columns.

Supports a tiny whitelisted grammar covering comparisons, arithmetic, and
boolean logic. Operands are column references or numeric / string / boolean
literals. Untrusted expressions are validated and evaluated against a row dict
without invoking the built-in ``eval`` or ``exec``.
"""
from __future__ import annotations

import ast
import operator
import re
from typing import Any

from ..utils import ApiError

_BIN_OPS = {
    ast.Add: operator.add,
    ast.Sub: operator.sub,
    ast.Mult: operator.mul,
    ast.Div: operator.truediv,
    ast.Mod: operator.mod,
    ast.Pow: operator.pow,
}

_CMP_OPS = {
    ast.Eq: operator.eq,
    ast.NotEq: operator.ne,
    ast.Lt: operator.lt,
    ast.LtE: operator.le,
    ast.Gt: operator.gt,
    ast.GtE: operator.ge,
    ast.In: lambda a, b: a in b,
    ast.NotIn: lambda a, b: a not in b,
}

_BOOL_OPS = {
    ast.And: all,
    ast.Or: any,
}

_UNARY_OPS = {
    ast.Not: operator.not_,
    ast.USub: operator.neg,
    ast.UAdd: operator.pos,
}

_COLUMN_PATTERN = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")


class ExpressionError(ApiError):
    def __init__(self, message: str):
        super().__init__(message, status=400, code="bad_request")


def _coerce_literal(node: ast.Constant) -> Any:
    if isinstance(node.value, (int, float, str, bool)) or node.value is None:
        return node.value
    raise ExpressionError(f"Literal type '{type(node.value).__name__}' is not allowed")


def _lookup(node: ast.Name, row: dict[str, Any], schema_columns: set[str]) -> Any:
    name = node.id
    if name in {"True", "False", "None"}:
        return {"True": True, "False": False, "None": None}[name]
    if not _COLUMN_PATTERN.match(name):
        raise ExpressionError(f"Invalid identifier '{name}'")
    if schema_columns and name not in schema_columns:
        raise ExpressionError(f"Unknown column '{name}'")
    return row.get(name)


def _evaluate(node: ast.AST, row: dict[str, Any], schema_columns: set[str]) -> Any:
    if isinstance(node, ast.Expression):
        return _evaluate(node.body, row, schema_columns)
    if isinstance(node, ast.Constant):
        return _coerce_literal(node)
    if isinstance(node, ast.Name):
        return _lookup(node, row, schema_columns)
    if isinstance(node, ast.UnaryOp):
        op_type = type(node.op)
        if op_type not in _UNARY_OPS:
            raise ExpressionError(f"Unary operator '{op_type.__name__}' is not allowed")
        return _UNARY_OPS[op_type](_evaluate(node.operand, row, schema_columns))
    if isinstance(node, ast.BinOp):
        op_type = type(node.op)
        if op_type not in _BIN_OPS:
            raise ExpressionError(f"Binary operator '{op_type.__name__}' is not allowed")
        left = _evaluate(node.left, row, schema_columns)
        right = _evaluate(node.right, row, schema_columns)
        return _BIN_OPS[op_type](left, right)
    if isinstance(node, ast.BoolOp):
        op_type = type(node.op)
        if op_type not in _BOOL_OPS:
            raise ExpressionError("Unsupported boolean operator")
        values = [_evaluate(value, row, schema_columns) for value in node.values]
        return _BOOL_OPS[op_type](values)
    if isinstance(node, ast.Compare):
        if len(node.ops) != 1 or len(node.comparators) != 1:
            raise ExpressionError("Only single-comparator comparisons are supported")
        op_type = type(node.ops[0])
        if op_type not in _CMP_OPS:
            raise ExpressionError(f"Comparison operator '{op_type.__name__}' is not allowed")
        left = _evaluate(node.left, row, schema_columns)
        right = _evaluate(node.comparators[0], row, schema_columns)
        # ast.In expects the left to be the element and right to be the container,
        # which is already the case from the parser.
        try:
            return _CMP_OPS[op_type](left, right)
        except TypeError as exc:
            raise ExpressionError(f"Type mismatch in comparison: {exc}") from exc
    raise ExpressionError(f"Unsupported expression node '{type(node).__name__}'")


def validate_expression(expression: str, schema_columns: set[str]) -> ast.AST:
    """Parse and validate an expression without evaluating it."""
    if not isinstance(expression, str) or not expression.strip():
        raise ExpressionError("Expression is required")
    try:
        tree = ast.parse(expression, mode="eval")
    except SyntaxError as exc:
        raise ExpressionError(f"Invalid syntax: {exc.msg}") from exc
    # Walk the tree to ensure no disallowed node types
    for sub in ast.walk(tree):
        if isinstance(sub, ast.Call):
            raise ExpressionError("Function calls are not allowed in expressions")
        if isinstance(sub, ast.Attribute):
            raise ExpressionError("Attribute access is not allowed in expressions")
        if isinstance(sub, (ast.List, ast.Tuple, ast.Set)):
            raise ExpressionError("Literal collections are not allowed; use IN with a list variable")
        if isinstance(sub, ast.Subscript):
            raise ExpressionError("Subscript access is not allowed in expressions")
        if isinstance(sub, ast.IfExp):
            raise ExpressionError("Conditional expressions are not allowed")
        if isinstance(sub, ast.Lambda):
            raise ExpressionError("Lambda expressions are not allowed")
    # Validate column references against schema
    for node in ast.walk(tree):
        if isinstance(node, ast.Name) and node.id not in {"True", "False", "None"}:
            if not _COLUMN_PATTERN.match(node.id):
                raise ExpressionError(f"Invalid identifier '{node.id}'")
            if schema_columns and node.id not in schema_columns:
                raise ExpressionError(f"Unknown column '{node.id}'")
    return tree


def evaluate_expression(
    expression: str,
    row: dict[str, Any],
    schema_columns: set[str] | None = None,
) -> Any:
    """Validate and evaluate an expression against a single row."""
    columns = schema_columns if schema_columns is not None else set(row.keys())
    tree = validate_expression(expression, columns)
    return _evaluate(tree, row, columns)


def apply_filter(
    rows: list[dict[str, Any]],
    expression: str,
    schema_columns: set[str],
) -> list[dict[str, Any]]:
    if not expression or not expression.strip():
        return rows
    tree = validate_expression(expression, schema_columns)
    matched: list[dict[str, Any]] = []
    for row in rows:
        try:
            if bool(_evaluate(tree, row, schema_columns)):
                matched.append(row)
        except (TypeError, ValueError):
            continue
    return matched


def compute_calculated_columns(
    rows: list[dict[str, Any]],
    definitions: list[tuple[str, str]],
    schema_columns: set[str],
) -> list[dict[str, Any]]:
    if not definitions:
        return rows
    compiled: list[tuple[str, ast.AST, set[str]]] = []
    for name, expression in definitions:
        compiled.append((name, validate_expression(expression, schema_columns), schema_columns))
    for row in rows:
        for name, tree, cols in compiled:
            try:
                row[name] = _evaluate(tree, row, cols)
            except (TypeError, ValueError):
                row[name] = None
    return rows


def summarize(rows: list[dict[str, Any]], schema: list[dict[str, str]]) -> dict[str, Any]:
    columns = []
    for column in schema:
        name = column["name"]
        dtype = column["type"]
        values = [row.get(name) for row in rows]
        non_null = [v for v in values if v is not None and v != ""]
        summary: dict[str, Any] = {
            "name": name,
            "type": dtype,
            "count": len(values),
            "non_null": len(non_null),
            "missing": len(values) - len(non_null),
        }
        numeric = []
        if dtype in {"integer", "number"}:
            for v in non_null:
                try:
                    numeric.append(float(v))
                except (TypeError, ValueError):
                    pass
        if numeric:
            summary["min"] = min(numeric)
            summary["max"] = max(numeric)
            summary["mean"] = sum(numeric) / len(numeric)
        elif dtype in {"string"} and non_null:
            summary["distinct"] = len({str(v) for v in non_null})
        columns.append(summary)
    return {"row_count": len(rows), "columns": columns}
