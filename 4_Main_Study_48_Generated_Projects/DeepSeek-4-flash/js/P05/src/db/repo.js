function one(db, sql, params = {}) {
  return db.prepare(sql).get(params);
}

function all(db, sql, params = {}) {
  return db.prepare(sql).all(params);
}

function run(db, sql, params = {}) {
  return db.prepare(sql).run(params);
}

export function createRepo(db, table, opts = {}) {
  const { fields = [], orderBy = `${table}.id DESC`, defaultSelect = '*', jsonFields = [] } = opts;

  function parseJson(row) {
    if (!row) return row;
    for (const f of jsonFields) {
      if (row[f] !== undefined && row[f] !== null && typeof row[f] === 'string') {
        try {
          row[f] = JSON.parse(row[f]);
        } catch {
          row[f] = [];
        }
      }
    }
    return row;
  }

  function pick(data) {
    const out = {};
    for (const f of fields) {
      if (data[f] !== undefined) out[f] = data[f];
    }
    return out;
  }

  function list({ where = '', params = {}, page = 1, pageSize = 50, search } = {}) {
    const whereSql = where ? ` WHERE ${where}` : '';
    const searchSql = search ? `${where ? ' AND' : ' WHERE'} (${opts.searchable || '1=0'})` : '';
    const total = one(db, `SELECT COUNT(*) AS count FROM ${table}${whereSql}${searchSql}`, search ? { ...params, q: `%${search}%` } : params).count;
    const rows = all(
      db,
      `SELECT ${defaultSelect} FROM ${table}${whereSql}${searchSql} ORDER BY ${orderBy} LIMIT ${Math.max(1, Number(pageSize) || 50)} OFFSET ${(Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50))}`,
      search ? { ...params, q: `%${search}%` } : params
    );
    return { data: rows.map(parseJson), total };
  }

  function findOne(where = '', params = {}) {
    const row = one(db, `SELECT ${defaultSelect} FROM ${table}${where ? ` WHERE ${where}` : ''} LIMIT 1`, params);
    return parseJson(row);
  }

  function findById(id) {
    return parseJson(one(db, `SELECT ${defaultSelect} FROM ${table} WHERE ${table}.id = ?`, [id]));
  }

  function create(data) {
    const clean = pick(data);
    const keys = Object.keys(clean);
    if (keys.length === 0) return null;
    const cols = keys.join(', ');
    const placeholders = keys.map((k) => `@${k}`).join(', ');
    const result = run(db, `INSERT INTO ${table} (${cols}) VALUES (${placeholders})`, clean);
    return findById(result.lastInsertRowid);
  }

  function update(id, data) {
    const clean = pick(data);
    const keys = Object.keys(clean);
    if (keys.length === 0) return findById(id);
    const sets = keys.map((k) => `${k} = @${k}`).join(', ');
    run(db, `UPDATE ${table} SET ${sets} WHERE id = @id`, { ...clean, id });
    return findById(id);
  }

  function remove(id) {
    const result = run(db, `DELETE FROM ${table} WHERE id = ?`, [id]);
    return result.changes > 0;
  }

  function count(where = '', params = {}) {
    return one(db, `SELECT COUNT(*) AS count FROM ${table}${where ? ` WHERE ${where}` : ''}`, params).count;
  }

  return { list, findOne, findById, create, update, remove, count, rawOne: one, rawAll: all, rawRun: run };
}
