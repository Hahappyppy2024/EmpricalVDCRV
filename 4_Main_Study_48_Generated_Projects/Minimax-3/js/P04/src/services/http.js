import { getDb } from '../db/connection.js';

export async function parseJsonBody(req) {
  return new Promise((resolve, reject) => {
    let data = '';
    req.on('data', chunk => { data += chunk; if (data.length > 1024 * 256) { req.destroy(); reject(new Error('payload too large')); } });
    req.on('end', () => {
      if (!data) return resolve({});
      try {
        const ct = (req.headers['content-type'] || '').toLowerCase();
        if (ct.includes('application/json')) {
          resolve(JSON.parse(data));
        } else if (ct.includes('application/x-www-form-urlencoded')) {
          const out = {};
          for (const [k, v] of new URLSearchParams(data)) out[k] = v;
          resolve(out);
        } else {
          resolve(JSON.parse(data));
        }
      } catch (e) {
        reject(new Error('invalid json'));
      }
    });
    req.on('error', reject);
  });
}

export function sendJson(res, status, payload) {
  res.statusCode = status;
  res.setHeader('content-type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(payload));
}

export function asyncRoute(fn) {
  return (req, res, next) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
}

export function getUser(id) { return getDb().prepare('SELECT id, email, display_name, role FROM users WHERE id = ?').get(id); }