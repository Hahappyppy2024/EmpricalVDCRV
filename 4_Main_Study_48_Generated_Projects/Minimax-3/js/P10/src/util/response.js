export function ok(res, data, status = 200) {
  res.status(status).json({ ok: true, data });
}

export function created(res, data) {
  ok(res, data, 201);
}

export function paginated(res, items, total, limit, offset) {
  ok(res, { items, total, limit, offset });
}
