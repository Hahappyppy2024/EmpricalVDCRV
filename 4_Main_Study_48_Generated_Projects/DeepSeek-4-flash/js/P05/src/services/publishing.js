import { getDb } from '../db/index.js';
import { apiError } from '../lib/http.js';
import { broadcast } from './realTime.js';

export const ARTICLE_STATUSES = ['draft', 'review', 'scheduled', 'published'];

export const TRANSITIONS = {
  draft: ['review'],
  review: ['scheduled', 'published', 'draft'],
  scheduled: ['published', 'review'],
  published: ['draft']
};

export function canTransition(fromStatus, toStatus, actorRole) {
  const allowed = TRANSITIONS[fromStatus] || [];
  if (!allowed.includes(toStatus)) {
    return { ok: false, reason: 'invalid_transition', message: `Transition from "${fromStatus}" to "${toStatus}" is not allowed.` };
  }
  if (fromStatus === 'draft' && toStatus === 'review' && !['author', 'editor', 'admin'].includes(actorRole)) {
    return { ok: false, reason: 'forbidden', message: 'Only an author or editor can submit content for review.' };
  }
  if (fromStatus === 'review' && !['editor', 'admin'].includes(actorRole)) {
    return { ok: false, reason: 'forbidden', message: 'Only an editor or admin can decide the outcome of a review.' };
  }
  if (fromStatus === 'scheduled' && !['editor', 'admin'].includes(actorRole)) {
    return { ok: false, reason: 'forbidden', message: 'Only an editor or admin can manage scheduled content.' };
  }
  if (fromStatus === 'published' && !['editor', 'admin'].includes(actorRole)) {
    return { ok: false, reason: 'forbidden', message: 'Only an editor or admin can unpublish content.' };
  }
  return { ok: true };
}

export function applyTransition({ article, toStatus, actor, note }) {
  const db = getDb();

  if (article.status === toStatus) {
    throw apiError(422, 'NO_OP_TRANSITION', `Article is already in "${toStatus}" state.`);
  }
  const check = canTransition(article.status, toStatus, actor.role_name);
  if (!check.ok) {
    throw apiError(422, check.reason.toUpperCase(), check.message);
  }
  if (toStatus === 'scheduled' && !article.publish_at) {
    throw apiError(422, 'PUBLISH_DATE_REQUIRED', 'Scheduling requires a future publish date (publish_at).');
  }
  if (toStatus === 'published' && article.status === 'scheduled' && article.publish_at && article.publish_at > new Date().toISOString().slice(0, 19).replace('T', ' ')) {
    throw apiError(422, 'PUBLISH_DATE_FUTURE', 'This article is scheduled for a future date and cannot be published early.');
  }

  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const tx = db.transaction(() => {
    if (toStatus === 'published') {
      db.prepare(`UPDATE articles SET status = 'published', editor_id = COALESCE(editor_id, ?), published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?`)
        .run(actor.id, now, now, article.id);
    } else if (toStatus === 'scheduled') {
      db.prepare(`UPDATE articles SET status = 'scheduled', editor_id = ?, updated_at = ? WHERE id = ?`)
        .run(actor.id, now, article.id);
    } else if (toStatus === 'draft') {
      db.prepare(`UPDATE articles SET status = 'draft', updated_at = ? WHERE id = ?`).run(now, article.id);
    } else if (toStatus === 'review') {
      db.prepare(`UPDATE articles SET status = 'review', updated_at = ? WHERE id = ?`).run(now, article.id);
    }
    db.prepare(`INSERT INTO publishing_workflow (article_id, from_status, to_status, actor_id, note, created_at)
      VALUES (?, ?, ?, ?, ?, ?)`)
      .run(article.id, article.status, toStatus, actor.id, note || null, now);
  });
  tx();

  const record = db.prepare(`
    SELECT w.*, a.title AS article_title, a.slug AS article_slug, u.username AS actor_username
    FROM publishing_workflow w
    JOIN articles a ON a.id = w.article_id
    LEFT JOIN users u ON u.id = w.actor_id
    WHERE w.id = (SELECT MAX(id) FROM publishing_workflow WHERE article_id = ?)
  `).get(article.id);

  broadcast('publishing:transition', {
    articleId: article.id,
    articleTitle: article.title,
    fromStatus: article.status,
    toStatus,
    actor: actor.username,
    note: note || null
  });
  broadcast('content:status', { articleId: article.id, status: toStatus });

  return record;
}

export function flushScheduled(db) {
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  const due = db.prepare(`SELECT * FROM articles WHERE status = 'scheduled' AND publish_at IS NOT NULL AND publish_at <= ?`).all(now);
  if (due.length === 0) return 0;
  const tx = db.transaction(() => {
    for (const article of due) {
      db.prepare(`UPDATE articles SET status = 'published', published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?`).run(now, now, article.id);
      db.prepare(`INSERT INTO publishing_workflow (article_id, from_status, to_status, actor_id, note, created_at)
        VALUES (?, 'scheduled', 'published', ?, 'Auto-published at scheduled time', ?)`)
        .run(article.id, article.editor_id || article.author_id, now);
      broadcast('publishing:transition', {
        articleId: article.id,
        articleTitle: article.title,
        fromStatus: 'scheduled',
        toStatus: 'published',
        actor: 'system',
        note: 'Auto-published at scheduled time'
      });
    }
  });
  tx();
  return due.length;
}
