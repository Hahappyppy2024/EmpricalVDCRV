import { Router } from 'express';
import { HttpError } from '../middleware/error.js';

export function shareRouter(db) {
  const router = Router();

  router.get('/:token', (req, res) => {
    const row = db.prepare('SELECT * FROM share_conversations WHERE token = ?').get(req.params.token);
    if (!row || row.revoked) {
      throw new HttpError(404, 'Share link not found or revoked', 'NOT_FOUND');
    }
    if (row.expires_at && new Date(row.expires_at) < new Date()) {
      throw new HttpError(410, 'Share link has expired', 'SHARE_EXPIRED');
    }
    const conversation = db.prepare('SELECT id, title, status, created_at FROM conversations WHERE id = ?').get(row.conversation_id);
    if (!conversation) {
      throw new HttpError(404, 'Shared conversation not found', 'NOT_FOUND');
    }
    const owner = db.prepare('SELECT id, username FROM users WHERE id = ?').get(row.user_id);
    const messages = db
      .prepare('SELECT id, role, content, citations, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC')
      .all(row.conversation_id)
      .map((m) => ({ ...m, citations: m.citations ? JSON.parse(m.citations) : null }));
    res.json({
      ok: true,
      share: { token: row.token, created_at: row.created_at, expires_at: row.expires_at },
      conversation,
      owner: owner ? { username: owner.username } : null,
      messages,
    });
  });

  return router;
}
