const auditRepo = require('../repos/auditRepo');

function isApi(req) {
  return (req.originalUrl || '').startsWith('/api');
}


function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[c]));
}

function getAdminAuditPage(req, res) {
  const me = req.session ? req.session.user : null;

  try {
    auditRepo.create({
      actor_user_id: me?.user_id ?? null,
      actor_username: me?.username ?? null,
      action: 'audit_view',
      target_type: null,
      target_id: null,
      meta_json: { via: 'web' }
    });
  } catch (e) {
    // avoid breaking the page if logging fails
  } 

  let rows = [];
  try {
    rows = auditRepo.listRecent(200);
  } catch (e) {
    rows = [];
  }

  try {
    return res.render('admin/audit', { rows });
  } catch (e) {
    return res.status(200).send(`
      <html><body>
        <h1>Audit Log</h1>
        <pre>${escapeHtml(JSON.stringify(rows, null, 2))}</pre>
      </body></html>
    `);
  }
}

function getAdminAuditApi(req, res) {
  const me = req.session ? req.session.user : null;

  try {
    auditRepo.create({
      actor_user_id: me?.user_id ?? null,
      actor_username: me?.username ?? null,
      action: 'audit_view',
      target_type: null,
      target_id: null,
      meta_json: { via: 'api' }
    });
  } catch (e) {
    // ignore
  }

  let rows = [];
  try {
    rows = auditRepo.listRecent(200);
  } catch (e) {
    rows = [];
  }

  return res.status(200).json({ audit: rows });
}

module.exports = { getAdminAuditPage, getAdminAuditApi };