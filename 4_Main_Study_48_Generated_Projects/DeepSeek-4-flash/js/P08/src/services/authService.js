import { getDb } from '../db/database.js';
import { hashPassword, verifyPassword, randomToken } from '../lib/crypto.js';
import { badRequest, forbidden, notFound, unauthorized } from '../lib/errors.js';
import { config } from '../config.js';
import { writeAudit } from './auditService.js';

const ROLES = ['employee', 'manager', 'finance', 'admin'];

export function publicUser(user) {
  if (!user) return null;
  return {
    id: user.id,
    username: user.username,
    full_name: user.full_name,
    email: user.email,
    role: user.role,
    department_id: user.department_id,
    cost_center_id: user.cost_center_id,
    manager_id: user.manager_id,
    active: !!user.active,
    created_at: user.created_at,
  };
}

export function findUserByUsername(db, username) {
  return db.prepare('SELECT * FROM users WHERE username = ?').get(username);
}

export function findUserById(db, id) {
  return db.prepare('SELECT * FROM users WHERE id = ?').get(id);
}

function createSession(db, user, req) {
  const id = randomToken(32);
  const createdAt = new Date();
  const expiresAt = new Date(createdAt.getTime() + config.sessionTtlHours * 3600 * 1000);
  const fmt = (d) => d.toISOString().replace('T', ' ').slice(0, 19);
  db.prepare(
    `INSERT INTO sessions (id, user_id, created_at, expires_at, ip, user_agent)
     VALUES (?, ?, ?, ?, ?, ?)`
  ).run(id, user.id, fmt(createdAt), fmt(expiresAt), req.ip || null, (req.headers['user-agent'] || '').slice(0, 300));
  return { id, createdAt: fmt(createdAt), expiresAt: fmt(expiresAt) };
}

export function setSessionCookie(res, sessionId) {
  res.cookie(config.sessionCookieName, sessionId, {
    httpOnly: true,
    sameSite: 'lax',
    secure: config.isProduction,
    path: '/',
    maxAge: config.sessionTtlHours * 3600 * 1000,
  });
}

export function clearSessionCookie(res) {
  res.clearCookie(config.sessionCookieName, { path: '/' });
}

export function login(db, { username, password, ip, userAgent }) {
  const user = findUserByUsername(db, username);
  if (!user || !verifyPassword(password, user.password_hash)) {
    throw unauthorized('Invalid username or password');
  }
  if (!user.active) throw forbidden('This account is disabled');
  const session = createSession(db, user, { ip, headers: { 'user-agent': userAgent } });
  writeAudit(db, { actorId: user.id, action: 'auth.login', entityType: 'session', entityId: session.id });
  return { user: publicUser(user), session };
}

export function register(db, { username, password, fullName, email, departmentId, costCenterId, managerId }) {
  if (!username || !password || !fullName || !email) {
    throw badRequest('username, password, full_name and email are required');
  }
  if (String(password).length < 6) throw badRequest('Password must be at least 6 characters');
  if (findUserByUsername(db, username)) throw badRequest('Username is already taken');
  const info = db
    .prepare(
      `INSERT INTO users (username, password_hash, full_name, email, role, department_id, cost_center_id, manager_id, active, created_at)
       VALUES (?, ?, ?, ?, 'employee', ?, ?, ?, 1, datetime('now'))`
    )
    .run(username, hashPassword(password), fullName, email, departmentId || null, costCenterId || null, managerId || null);
  const user = findUserById(db, info.lastInsertRowid);
  writeAudit(db, { actorId: user.id, action: 'auth.register', entityType: 'user', entityId: user.id });
  return publicUser(user);
}

export function logout(db, req, res) {
  if (req.session) {
    db.prepare('DELETE FROM sessions WHERE id = ?').run(req.session.id);
    writeAudit(db, { actorId: req.user ? req.user.id : null, action: 'auth.logout', entityType: 'session', entityId: req.session.id });
  }
  clearSessionCookie(res);
  return { ok: true };
}

export function listSessions(db, userId) {
  return db.prepare('SELECT id, created_at, expires_at, ip, user_agent FROM sessions WHERE user_id = ? ORDER BY created_at DESC').all(userId);
}

export function revokeSession(db, userId, sessionId) {
  const session = db.prepare('SELECT * FROM sessions WHERE id = ?').get(sessionId);
  if (!session) throw notFound('Session not found');
  if (session.user_id !== userId) throw forbidden('You cannot revoke another user\'s session');
  db.prepare('DELETE FROM sessions WHERE id = ?').run(sessionId);
  writeAudit(db, { actorId: userId, action: 'auth.session_revoked', entityType: 'session', entityId: sessionId });
  return { ok: true };
}

export function updateProfile(db, user, { fullName, email, oldPassword, newPassword }) {
  const current = findUserById(db, user.id);
  if (fullName !== undefined) {
    if (!String(fullName).trim()) throw badRequest('full_name cannot be empty');
    db.prepare('UPDATE users SET full_name = ? WHERE id = ?').run(String(fullName).trim(), user.id);
  }
  if (email !== undefined) {
    if (!String(email).trim() || !String(email).includes('@')) throw badRequest('A valid email is required');
    db.prepare('UPDATE users SET email = ? WHERE id = ?').run(String(email).trim(), user.id);
  }
  if (oldPassword !== undefined || newPassword !== undefined) {
    if (!verifyPassword(oldPassword, current.password_hash)) throw badRequest('Current password is incorrect');
    if (String(newPassword).length < 6) throw badRequest('New password must be at least 6 characters');
    db.prepare('UPDATE users SET password_hash = ? WHERE id = ?').run(hashPassword(newPassword), user.id);
  }
  writeAudit(db, { actorId: user.id, action: 'account.updated', entityType: 'user', entityId: user.id });
  return publicUser(findUserById(db, user.id));
}

export function dashboardFor(db, user) {
  const rows = db
    .prepare(
      `SELECT status, COUNT(*) AS n FROM expense_reports GROUP BY status`
    )
    .all();
  const counts = { draft: 0, submitted: 0, approved: 0, rejected: 0, changes_requested: 0, finance_approved: 0, finance_rejected: 0, reimbursed: 0 };
  for (const r of rows) counts[r.status] = r.n;

  const mine = db.prepare('SELECT COUNT(*) AS n FROM expense_reports WHERE employee_id = ?').get(user.id).n;
  const pendingManager = db.prepare('SELECT COUNT(*) AS n FROM expense_reports WHERE submitted_to_id = ? AND status = ?').get(user.id, 'submitted').n;
  const pendingFinance = db.prepare("SELECT COUNT(*) AS n FROM expense_reports WHERE status = 'approved'").get().n;
  const rulesEnabled = db.prepare('SELECT COUNT(*) AS n FROM policy_rules WHERE enabled = 1').get().n;
  const myEmployees = db.prepare('SELECT COUNT(*) AS n FROM users WHERE manager_id = ? AND active = 1').get(user.id).n;
  const myTotal = db.prepare('SELECT COALESCE(SUM(total_amount),0) AS n FROM expense_reports WHERE employee_id = ?').get(user.id).n;

  return {
    user: publicUser(user),
    role: user.role,
    counts,
    per_role: {
      employee: { my_reports: mine, my_total_amount: myTotal },
      manager: { pending_approvals: pendingManager, direct_reports: myEmployees },
      finance: { pending_reviews: pendingFinance, enabled_policy_rules: rulesEnabled },
      admin: { enabled_policy_rules: rulesEnabled, departments: db.prepare('SELECT COUNT(*) AS n FROM departments').get().n },
    },
  };
}

export { ROLES };
