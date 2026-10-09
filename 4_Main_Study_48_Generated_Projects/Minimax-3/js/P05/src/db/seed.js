import { getDb, initSchema, resetDatabase } from './database.js';
import { hashPassword } from '../services/password.js';

export function seed() {
  resetDatabase();
  const db = getDb();

  const insertRole = db.prepare('INSERT INTO roles (name, description) VALUES (?, ?)');
  const roleIds = {};
  for (const r of [
    ['admin', 'Full administrative access'],
    ['editor', 'Review, publish, templates'],
    ['author', 'Create and manage own drafts'],
    ['moderator', 'Approve and remove comments'],
    ['visitor', 'Public visitor (registered commenter)']
  ]) {
    const info = insertRole.run(r[0], r[1]);
    roleIds[r[0]] = info.lastInsertRowid;
  }

  const insertUser = db.prepare(
    'INSERT INTO users (username, email, password_hash, display_name, role_id) VALUES (?, ?, ?, ?, ?)'
  );
  const users = [
    ['admin', 'admin@cms.local', 'admin123', 'Site Administrator', 'admin'],
    ['editor1', 'editor1@cms.local', 'editor123', 'Erica Editor', 'editor'],
    ['author1', 'author1@cms.local', 'author123', 'Adam Author', 'author'],
    ['author2', 'author2@cms.local', 'author123', 'Anita Author', 'author'],
    ['mod1', 'mod1@cms.local', 'moderator123', 'Mona Moderator', 'moderator'],
    ['visitor1', 'visitor1@cms.local', 'visitor123', 'Vince Visitor', 'visitor']
  ];
  const userIds = {};
  for (const u of users) {
    const info = insertUser.run(u[0], u[1], hashPassword(u[2]), u[3], roleIds[u[4]]);
    userIds[u[0]] = info.lastInsertRowid;
  }

  // Categories
  const insertCategory = db.prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
  const catIds = {};
  for (const c of [['News', 'news'], ['Tutorials', 'tutorials'], ['Announcements', 'announcements']]) {
    const info = insertCategory.run(c[0], c[1]);
    catIds[c[0]] = info.lastInsertRowid;
  }

  // Page templates
  const insertTemplate = db.prepare(
    'INSERT INTO page_templates (name, description, layout_html, regions, is_default) VALUES (?, ?, ?, ?, ?)'
  );
  const tplIds = {};
  const tplData = [
    ['Default Article', 'Standard blog layout with header and footer', '<article class="tpl-default">{{body}}</article>', '["main"]', 1],
    ['Wide Hero', 'Hero image plus content', '<section class="tpl-hero"><h1>{{title}}</h1>{{body}}</section>', '["hero","main"]', 0],
    ['Sidebar Layout', 'Main content with sidebar', '<div class="tpl-sidebar"><main>{{body}}</main><aside>{{sidebar}}</aside></div>', '["main","sidebar"]', 0]
  ];
  for (const t of tplData) {
    const info = insertTemplate.run(...t);
    tplIds[t[0]] = info.lastInsertRowid;
  }

  // Navigation
  const insertNav = db.prepare('INSERT INTO navigation_menus (label, url, sort_order) VALUES (?, ?, ?)');
  const navIds = {};
  for (const n of [['Home', '/', 1], ['Articles', '/articles', 2], ['About', '/about', 3], ['Contact', '/contact', 4]]) {
    const info = insertNav.run(n[0], n[1], n[2]);
    navIds[n[0]] = info.lastInsertRowid;
  }

  // Articles
  const insertArticle = db.prepare(
    'INSERT INTO content_authoring (title, slug, summary, body, tags, status, author_id, category_id, template_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  const articles = [
    ['Welcome to the CMS', 'welcome-to-the-cms', 'An introductory overview of the system.', '<p>This is the welcome article. The CMS provides content authoring, publishing workflow, and media library features.</p>', 'intro,cms', 'published', userIds.author1, catIds['News'], tplIds['Default Article']],
    ['How to write a tutorial', 'how-to-write-a-tutorial', 'A short guide to writing great tutorials.', '<p>Tutorials should focus on a single goal, contain copy-pasteable examples, and finish with a recap.</p>', 'tutorial,guide', 'published', userIds.author2, catIds['Tutorials'], tplIds['Default Article']],
    ['Q3 Roadmap', 'q3-roadmap', 'Highlights for the upcoming quarter.', '<p>This quarter focuses on improving media uploads and the plugin settings panel.</p>', 'roadmap,planning', 'review', userIds.author1, catIds['Announcements'], tplIds['Wide Hero']],
    ['Draft: experiments', 'draft-experiments', 'Work in progress for new layouts.', '<p>Experiments with the sidebar template.</p>', 'experimental', 'draft', userIds.author2, catIds['Tutorials'], tplIds['Sidebar Layout']]
  ];
  const articleIds = {};
  for (const a of articles) {
    const info = insertArticle.run(...a);
    articleIds[a[1]] = info.lastInsertRowid;
  }

  // Rich text editor revisions
  const insertRte = db.prepare(
    'INSERT INTO rich_text_editor (article_id, body_html, css_classes, preview_token, updated_by) VALUES (?, ?, ?, ?, ?)'
  );
  for (const slug of ['welcome-to-the-cms', 'how-to-write-a-tutorial', 'q3-roadmap']) {
    insertRte.run(articleIds[slug], `<p>Rich text for ${slug}</p>`, 'cms-prose', `prev_${slug}_${articleIds[slug]}`, userIds.author1);
  }

  // Media library seed (placeholder records only; actual files copied at runtime)
  const insertMedia = db.prepare(
    'INSERT INTO media_library (file_name, original_name, mime_type, size_bytes, url_path, owner_id, alt_text, is_private) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
  );
  insertMedia.run('seed-banner.png', 'banner.png', 'image/png', 2048, '/uploads/seed-banner.png', userIds.admin, 'Site banner', 0);
  insertMedia.run('seed-logo.svg', 'logo.svg', 'image/svg+xml', 512, '/uploads/seed-logo.svg', userIds.admin, 'Site logo', 0);

  // Publishing workflow
  const insertWorkflow = db.prepare(
    'INSERT INTO publishing_workflow (article_id, state, scheduled_at, published_at, reviewer_id, notes) VALUES (?, ?, ?, ?, ?, ?)'
  );
  for (const a of articles) {
    const state = a[5] === 'published' ? 'published' : (a[5] === 'review' ? 'review' : 'draft');
    const scheduled = state === 'review' ? '2026-09-01 10:00:00' : null;
    const published = state === 'published' ? new Date().toISOString().replace('T', ' ').substring(0, 19) : null;
    insertWorkflow.run(articleIds[a[1]], state, scheduled, published, state === 'review' ? userIds.editor1 : null, state === 'review' ? 'Ready for editor review' : null);
  }

  // Public site entries (mirror published articles)
  const insertPublic = db.prepare(
    'INSERT INTO public_site (route_path, title, excerpt, body, article_id, template_id) VALUES (?, ?, ?, ?, ?, ?)'
  );
  for (const a of articles.filter(a => a[5] === 'published')) {
    insertPublic.run(`/articles/${a[1]}`, a[0], a[2], a[3], articleIds[a[1]], tplIds[a[8]]);
  }
  insertPublic.run('/', 'Home', 'Welcome to the CMS', '<p>Public homepage served by the CMS.</p>', null, tplIds['Default Article']);

  // Comments
  const insertComment = db.prepare(
    'INSERT INTO comments (article_id, author_name, author_email, author_id, body, state, moderator_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  insertComment.run(articleIds['welcome-to-the-cms'], 'Vince Visitor', 'visitor1@cms.local', userIds.visitor1, 'Great introduction, thanks!', 'approved', userIds.mod1);
  insertComment.run(articleIds['welcome-to-the-cms'], 'Guest', null, null, 'Looking forward to more posts.', 'pending', null);
  insertComment.run(articleIds['how-to-write-a-tutorial'], 'Vince Visitor', 'visitor1@cms.local', userIds.visitor1, 'Helpful structure.', 'approved', userIds.mod1);

  // Account access log seed
  db.prepare('INSERT INTO account_access (user_id, event, ip_address) VALUES (?, ?, ?)').run(userIds.admin, 'seed', '127.0.0.1');

  // User and role management history
  db.prepare('INSERT INTO user_and_role_management (user_id, new_role_id, action, performed_by) VALUES (?, ?, ?, ?)')
    .run(userIds.editor1, roleIds['editor'], 'assigned', userIds.admin);

  // Audit events
  db.prepare('INSERT INTO audit_events (actor_id, action, resource, details) VALUES (?, ?, ?, ?)')
    .run(userIds.admin, 'system.bootstrap', 'system', 'Initial seed data created');

  // Plugin / settings panel
  const insertSetting = db.prepare(
    'INSERT INTO plugin_settings_panel (key, value, category, updated_by) VALUES (?, ?, ?, ?)'
  );
  const settings = [
    ['site.title', 'Synthetic CMS', 'general', userIds.admin],
    ['site.description', 'A benchmark content management system', 'general', userIds.admin],
    ['site.timezone', 'UTC', 'general', userIds.admin],
    ['comments.require_moderation', 'true', 'comments', userIds.admin],
    ['comments.allow_anonymous', 'false', 'comments', userIds.admin],
    ['media.allowed_types', 'image/png,image/jpeg,image/svg+xml,image/gif,application/pdf', 'media', userIds.admin],
    ['media.max_size_bytes', '10485760', 'media', userIds.admin],
    ['plugin.cache.enabled', 'true', 'plugin', userIds.admin],
    ['plugin.search.enabled', 'true', 'plugin', userIds.admin],
    ['plugin.analytics.enabled', 'false', 'plugin', userIds.admin],
    ['theme.primary_color', '#2563eb', 'theme', userIds.admin],
    ['theme.layout', 'default', 'theme', userIds.admin]
  ];
  for (const s of settings) {
    insertSetting.run(...s);
  }

  // Import/export history
  db.prepare('INSERT INTO import_export (job_type, status, payload_json, file_path, requested_by, result_count, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
    .run('export', 'completed', JSON.stringify({ kind: 'initial' }), './uploads/seed-export.json', userIds.admin, articles.length, new Date().toISOString().replace('T', ' ').substring(0, 19));

  // Frontend API integration log
  db.prepare('INSERT INTO frontend_api_integration_and_errors (endpoint, method, response_code, error_class, message, observed_by) VALUES (?, ?, ?, ?, ?, ?)')
    .run('/api/cms/public_site', 'GET', 200, null, 'ok', userIds.author1);

  console.log('Seed completed.');
  console.log('Roles seeded:', Object.keys(roleIds).length);
  console.log('Users seeded:', users.length);
  console.log('Articles seeded:', articles.length);
  return { roleIds, userIds, articleIds };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  initSchema();
  seed();
}
