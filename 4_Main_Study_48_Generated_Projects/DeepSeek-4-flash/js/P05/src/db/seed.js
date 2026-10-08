import fs from 'node:fs';
import path from 'node:path';
import env from '../config/env.js';
import { hashPassword } from '../lib/crypto.js';

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');

function insert(db, sql, params) {
  const info = db.prepare(sql).run(params);
  return Number(info.lastInsertRowid);
}

function insertMany(db, table, rows) {
  if (rows.length === 0) return;
  const cols = Object.keys(rows[0]);
  const placeholders = cols.map((c) => `@${c}`).join(', ');
  const stmt = db.prepare(`INSERT INTO ${table} (${cols.join(', ')}) VALUES (${placeholders})`);
  const tx = db.transaction((items) => {
    for (const item of items) stmt.run(item);
  });
  tx(rows);
}

function ensureDirs() {
  fs.mkdirSync(env.uploadDir, { recursive: true });
  fs.mkdirSync(env.exportDir, { recursive: true });
}

export function seed(db) {
  ensureDirs();
  db.pragma('foreign_keys = ON');

  const existing = db.prepare('SELECT COUNT(*) AS c FROM users').get().c;
  if (existing > 0) {
    console.log('[seed] Database already contains data; skipping seed.');
    return { skipped: true };
  }

  // ---- Roles ----
  const roles = {};
  insertMany(db, 'roles', [
    { name: 'admin', description: 'Full access to all content and administration features.' },
    { name: 'editor', description: 'Can review, schedule, publish and manage any content.' },
    { name: 'author', description: 'Can author, edit and submit own content for review.' },
    { name: 'moderator', description: 'Can moderate visitor comments.' },
    { name: 'visitor', description: 'Can browse the public site and post comments.' }
  ]);
  for (const row of db.prepare('SELECT id, name FROM roles').all()) roles[row.name] = row.id;

  // ---- Users ----
  const users = {};
  const userRows = [
    { username: 'admin', email: 'admin@example.com', password: 'admin123', role: 'admin', displayName: 'System Administrator' },
    { username: 'editor', email: 'editor@example.com', password: 'editor123', role: 'editor', displayName: 'Elena Editor' },
    { username: 'author', email: 'author@example.com', password: 'author123', role: 'author', displayName: 'Aaron Author' },
    { username: 'moderator', email: 'moderator@example.com', password: 'moderator123', role: 'moderator', displayName: 'Mona Moderator' },
    { username: 'visitor', email: 'visitor@example.com', password: 'visitor123', role: 'visitor', displayName: 'Vera Visitor' }
  ];
  for (const u of userRows) {
    const id = insert(db, `INSERT INTO users (username, email, password_hash, role_id, display_name, status, created_at, updated_at)
      VALUES (@username, @email, @hash, @roleId, @displayName, 'active', @now, @now)`, {
      username: u.username,
      email: u.email,
      hash: hashPassword(u.password),
      roleId: roles[u.role],
      displayName: u.displayName,
      now: now()
    });
    users[u.username] = id;
  }
  const adminId = users.admin;

  // ---- Account access seed log ----
  insertMany(db, 'account_access', [
    { user_id: adminId, action: 'signup', details: 'Seed account created', ip: '127.0.0.1', created_at: now() },
    { user_id: users.editor, action: 'signup', details: 'Seed account created', ip: '127.0.0.1', created_at: now() },
    { user_id: users.author, action: 'signup', details: 'Seed account created', ip: '127.0.0.1', created_at: now() },
    { user_id: users.author, action: 'signin', details: 'Demo sign-in from seed', ip: '127.0.0.1', created_at: now() },
    { user_id: users.moderator, action: 'signup', details: 'Seed account created', ip: '127.0.0.1', created_at: now() },
    { user_id: users.visitor, action: 'signup', details: 'Seed account created', ip: '127.0.0.1', created_at: now() }
  ]);

  // ---- Categories ----
  const cats = {};
  insertMany(db, 'categories', [
    { name: 'Technology', slug: 'technology', description: 'News and articles about technology.', created_at: now() },
    { name: 'Design', slug: 'design', description: 'Design systems, UX and front-end craft.', created_at: now() },
    { name: 'Product News', slug: 'product-news', description: 'Announcements from the product team.', created_at: now() }
  ]);
  for (const row of db.prepare('SELECT id, slug FROM categories').all()) cats[row.slug] = row.id;

  // ---- Page templates ----
  const templates = {};
  insertMany(db, 'page_templates', [
    {
      name: 'Blog Post',
      slug: 'blog-post',
      description: 'Standard blog post layout with featured image and body.',
      body: '<h1>{{title}}</h1><p class="meta">By {{author}} · {{date}}</p><div class="article-body">{{body}}</div>',
      created_by: adminId,
      created_at: now(),
      updated_at: now()
    },
    {
      name: 'Article with Sidebar',
      slug: 'article-with-sidebar',
      description: 'Article layout with a sidebar for related links.',
      body: '<div class="grid"><main><h1>{{title}}</h1><div class="article-body">{{body}}</div></main><aside>{{related}}</aside></div>',
      created_by: adminId,
      created_at: now(),
      updated_at: now()
    },
    {
      name: 'Landing Page',
      slug: 'landing-page',
      description: 'Full-width landing page layout.',
      body: '<section class="hero"><h1>{{title}}</h1><div class="article-body">{{body}}</div></section>',
      created_by: adminId,
      created_at: now(),
      updated_at: now()
    }
  ]);
  for (const row of db.prepare('SELECT id, slug FROM page_templates').all()) templates[row.slug] = row.id;

  // ---- Nav menus ----
  insertMany(db, 'nav_menus', [
    { label: 'Home', url: '/', position: 1, created_at: now() },
    { label: 'Technology', url: '/category/technology', position: 2, created_at: now() },
    { label: 'Design', url: '/category/design', position: 3, created_at: now() },
    { label: 'Product News', url: '/category/product-news', position: 4, created_at: now() },
    { label: 'About', url: '/page/about', position: 5, created_at: now() }
  ]);

  // ---- Seed media assets ----
  function writeAsset(storageName, content) {
    const filePath = path.join(env.uploadDir, storageName);
    fs.writeFileSync(filePath, content);
    return filePath;
  }

  const coverSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="640" height="360" fill="#2563eb"/><text x="40" y="200" font-family="Arial" font-size="42" fill="#ffffff">P05 Content Studio</text><text x="40" y="240" font-family="Arial" font-size="20" fill="#dbeafe">Synthetic CMS demo asset</text></svg>`;
  const logoSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="80" viewBox="0 0 200 80"><rect width="200" height="80" rx="12" fill="#0f172a"/><text x="20" y="50" font-family="Arial" font-size="28" fill="#38bdf8">P05 CMS</text></svg>`;
  const aboutTxt = `Welcome to P05 Content Studio.\n\nThis is a deterministic seed asset used to demonstrate the media library and public site.\nIt can be inserted into rich text articles.`;

  const coverPath = writeAsset('seed-cover.svg', coverSvg);
  const logoPath = writeAsset('seed-logo.svg', logoSvg);
  const aboutPath = writeAsset('seed-about.txt', aboutTxt);

  function addMedia(owner, storageName, originalName, mimeType, alt, visibility, kind = 'media') {
    const size = fs.statSync(path.join(env.uploadDir, storageName)).size;
    const sfId = insert(db, `INSERT INTO stored_file (original_name, storage_name, mime_type, size_bytes, owner_id, kind, created_at)
      VALUES (@originalName, @storageName, @mimeType, @size, @owner, @kind, @now)`, {
      originalName, storageName, mimeType, size, owner, kind, now: now()
    });
    return { stored_file_id: sfId, alt_text: alt, visibility, uploaded_by: owner };
  }

  const media = [];
  media.push(addMedia(users.author, 'seed-cover.svg', 'seed-cover.svg', 'image/svg+xml', 'P05 cover illustration', 'public'));
  media.push(addMedia(users.editor, 'seed-logo.svg', 'seed-logo.svg', 'image/svg+xml', 'P05 CMS logo', 'public'));
  media.push(addMedia(users.author, 'seed-about.txt', 'seed-about.txt', 'text/plain', 'About text asset', 'private'));
  insertMany(db, 'media_library', media.map((m) => ({ ...m, created_at: now() })));
  const mediaRows = db.prepare('SELECT id FROM media_library ORDER BY id').all();
  const mediaIds = mediaRows.map((r) => r.id);
  const coverMediaId = mediaIds[0];

  // ---- Articles ----
  const articleTemplates = {
    welcome: {
      title: 'Welcome to P05 Content Studio',
      slug: 'welcome-to-p05-content-studio',
      status: 'published',
      category: 'technology',
      tags: ['welcome', 'cms', 'content'],
      author: 'author',
      editor: 'editor',
      publish_at: null,
      published_at: '2026-01-10 09:00:00',
      body: `Welcome to P05 Content Studio!

This is the first article published on the synthetic content management system.

## What you can do here

- Author articles and manage your own content
- Format rich text with headings, lists and links
- Upload media and insert it into your articles
- Move content through the publishing workflow

We hope you enjoy exploring the platform.

Visit the Dashboard to get started.`,
      body_html: `<p>Welcome to P05 Content Studio!</p><p>This is the first article published on the synthetic content management system.</p><h2>What you can do here</h2><ul><li>Author articles and manage your own content</li><li>Format rich text with headings, lists and links</li><li>Upload media and insert it into your articles</li><li>Move content through the publishing workflow</li></ul><p>We hope you enjoy exploring the platform.</p><p><strong>Visit the Dashboard to get started.</strong></p>`
    },
    design: {
      title: 'Design Systems for Modern Web',
      slug: 'design-systems-for-modern-web',
      status: 'published',
      category: 'design',
      tags: ['design', 'systems', 'frontend'],
      author: 'author',
      editor: 'editor',
      publish_at: null,
      published_at: '2026-02-04 11:30:00',
      body: `A design system is the single source of truth for a product's visual language.

## Core principles

- Consistency across every screen
- Reusable components and tokens
- Clear documentation

A well-maintained system reduces design debt and speeds up delivery.`,
      body_html: `<p>A design system is the single source of truth for a product's visual language.</p><h2>Core principles</h2><ul><li>Consistency across every screen</li><li>Reusable components and tokens</li><li>Clear documentation</li></ul><p>A well-maintained system reduces design debt and speeds up delivery.</p>`
    },
    headless: {
      title: 'The Future of Headless CMS',
      slug: 'the-future-of-headless-cms',
      status: 'published',
      category: 'technology',
      tags: ['headless', 'cms', 'api'],
      author: 'editor',
      editor: 'editor',
      publish_at: null,
      published_at: '2026-03-02 08:00:00',
      body: `Headless CMS platforms separate content management from content delivery.

Editors author content in a friendly dashboard while developers consume it through a <strong>public API</strong>.

Learn how P05 delivers published content to any front-end.`,
      body_html: `<p>Headless CMS platforms separate content management from content delivery.</p><p>Editors author content in a friendly dashboard while developers consume it through a <strong>public API</strong>.</p><p>Learn how P05 delivers published content to any front-end.</p>`
    },
    scheduling: {
      title: 'Scheduling Content Like a Pro',
      slug: 'scheduling-content-like-a-pro',
      status: 'scheduled',
      category: 'product-news',
      tags: ['scheduling', 'workflow'],
      author: 'author',
      editor: 'editor',
      publish_at: '2099-06-01 10:00:00',
      published_at: null,
      body: `Scheduled content keeps your site fresh without manual work.

Set a future publish date and the platform transitions the article to published automatically.

This article is currently in the scheduled state.`,
      body_html: `<p>Scheduled content keeps your site fresh without manual work.</p><p>Set a future publish date and the platform transitions the article to published automatically.</p><p>This article is currently in the <em>scheduled</em> state.</p>`
    },
    guidelines: {
      title: 'Draft: Editorial Guidelines',
      slug: 'draft-editorial-guidelines',
      status: 'draft',
      category: 'design',
      tags: ['guidelines', 'editing'],
      author: 'author',
      editor: null,
      publish_at: null,
      published_at: null,
      body: `These guidelines are still a draft.

They will be submitted for review once finished.`,
      body_html: `<p>These guidelines are still a draft.</p><p>They will be submitted for review once finished.</p>`
    },
    performance: {
      title: 'Review: Performance Optimization',
      slug: 'review-performance-optimization',
      status: 'review',
      category: 'technology',
      tags: ['performance', 'review'],
      author: 'author',
      editor: 'editor',
      publish_at: null,
      published_at: null,
      body: `This article is in review and awaits an editor decision.

Measure, optimize, and measure again.`,
      body_html: `<p>This article is in review and awaits an editor decision.</p><p>Measure, optimize, and measure again.</p>`
    }
  };

  const articleIds = {};
  const tx = db.transaction(() => {
    for (const key of Object.keys(articleTemplates)) {
      const a = articleTemplates[key];
      const id = insert(db, `INSERT INTO articles (title, slug, body, body_html, tags, status, author_id, editor_id, category_id, template_id, featured_media_id, publish_at, published_at, created_at, updated_at)
        VALUES (@title, @slug, @body, @bodyHtml, @tags, @status, @authorId, @editorId, @categoryId, @templateId, @featuredMediaId, @publishAt, @publishedAt, @now, @now)`, {
        title: a.title,
        slug: a.slug,
        body: a.body,
        bodyHtml: a.body_html,
        tags: JSON.stringify(a.tags),
        status: a.status,
        authorId: users[a.author],
        editorId: a.editor ? users[a.editor] : null,
        categoryId: cats[a.category],
        templateId: templates['blog-post'],
        featuredMediaId: key === 'welcome' ? coverMediaId : null,
        publishAt: a.publish_at,
        publishedAt: a.published_at,
        now: now()
      });
      articleIds[key] = id;

      const firstUpdate = now();
      insert(db, `INSERT INTO rich_text_editor (article_id, content, content_html, version, updated_by, created_at, updated_at)
        VALUES (@articleId, @content, @contentHtml, 1, @updatedBy, @createdAt, @updatedAt)`, {
        articleId: id,
        content: a.body,
        contentHtml: a.body_html,
        updatedBy: users[a.author],
        createdAt: firstUpdate,
        updatedAt: firstUpdate
      });
    }
  });
  tx();

  // ---- Publishing workflow history ----
  insertMany(db, 'publishing_workflow', [
    { article_id: articleIds.welcome, from_status: 'draft', to_status: 'review', actor_id: users.author, note: 'Submitted for review', created_at: now() },
    { article_id: articleIds.welcome, from_status: 'review', to_status: 'published', actor_id: users.editor, note: 'Approved and published', created_at: now() },
    { article_id: articleIds.design, from_status: 'draft', to_status: 'review', actor_id: users.author, note: 'Submitted for review', created_at: now() },
    { article_id: articleIds.design, from_status: 'review', to_status: 'published', actor_id: users.editor, note: 'Approved and published', created_at: now() },
    { article_id: articleIds.headless, from_status: 'draft', to_status: 'review', actor_id: users.editor, note: 'Created directly', created_at: now() },
    { article_id: articleIds.headless, from_status: 'review', to_status: 'published', actor_id: users.editor, note: 'Published', created_at: now() },
    { article_id: articleIds.scheduling, from_status: 'draft', to_status: 'review', actor_id: users.author, note: 'Submitted for review', created_at: now() },
    { article_id: articleIds.scheduling, from_status: 'review', to_status: 'scheduled', actor_id: users.editor, note: 'Scheduled for 2099-06-01', created_at: now() },
    { article_id: articleIds.performance, from_status: 'draft', to_status: 'review', actor_id: users.author, note: 'Submitted for review', created_at: now() }
  ]);

  // ---- Comments ----
  insertMany(db, 'comments', [
    { article_id: articleIds.welcome, author_name: 'Vera Visitor', author_email: 'vera@example.com', body: 'Great first post, welcome to the platform!', status: 'approved', moderated_by: users.moderator, moderated_at: now(), created_at: now() },
    { article_id: articleIds.welcome, author_name: 'Quincy Reader', author_email: 'quincy@example.com', body: 'Looking forward to more content.', status: 'approved', moderated_by: users.moderator, moderated_at: now(), created_at: now() },
    { article_id: articleIds.welcome, author_name: 'Unknown User', author_email: 'nobody@example.com', body: 'This comment awaits moderation.', status: 'pending', moderated_by: null, moderated_at: null, created_at: now() },
    { article_id: articleIds.welcome, author_name: 'Spammer', author_email: 'spam@example.com', body: 'Buy cheap watches now!', status: 'removed', moderated_by: users.moderator, moderated_at: now(), created_at: now() },
    { article_id: articleIds.headless, author_name: 'Dev Dan', author_email: 'dan@example.com', body: 'Headless CMS is the way to go.', status: 'approved', moderated_by: users.moderator, moderated_at: now(), created_at: now() },
    { article_id: articleIds.headless, author_name: 'New Reader', author_email: 'reader@example.com', body: 'Please approve my comment.', status: 'pending', moderated_by: null, moderated_at: null, created_at: now() }
  ]);

  // ---- Public site page views ----
  insertMany(db, 'public_site', [
    { visitor_name: 'Vera Visitor', visitor_ip: '127.0.0.1', action: 'view', article_id: articleIds.welcome, referrer: null, details: 'homepage → article', created_at: now() },
    { visitor_name: 'Quincy Reader', visitor_ip: '127.0.0.1', action: 'view', article_id: articleIds.headless, referrer: null, details: 'search → article', created_at: now() },
    { visitor_name: null, visitor_ip: '127.0.0.1', action: 'search', article_id: null, referrer: null, details: 'q=cms', created_at: now() }
  ]);

  // ---- User and role management log ----
  insertMany(db, 'user_and_role_management', [
    { target_user_id: users.author, previous_role_id: roles.visitor, new_role_id: roles.author, action: 'role_change', actor_id: adminId, note: 'Promoted to author', created_at: now() },
    { target_user_id: users.moderator, previous_role_id: roles.visitor, new_role_id: roles.moderator, action: 'role_change', actor_id: adminId, note: 'Granted moderator role', created_at: now() }
  ]);

  // ---- Plugin / settings panel ----
  insertMany(db, 'plugin_settings_panel', [
    { key: 'site_name', value: 'P05 Content Studio', description: 'Public site name.', updated_by: adminId, updated_at: now() },
    { key: 'site_tagline', value: 'A synthetic content management system', description: 'Public site tagline.', updated_by: adminId, updated_at: now() },
    { key: 'allow_comments', value: 'true', description: 'Whether visitors can comment on published articles.', updated_by: adminId, updated_at: now() },
    { key: 'moderate_comments', value: 'true', description: 'New comments start as pending when moderation is on.', updated_by: adminId, updated_at: now() },
    { key: 'default_template', value: 'blog-post', description: 'Default template applied to new articles.', updated_by: adminId, updated_at: now() },
    { key: 'max_upload_mb', value: '10', description: 'Maximum media upload size in megabytes.', updated_by: adminId, updated_at: now() },
    { key: 'maintenance_mode', value: 'false', description: 'Shows a maintenance notice on the public site.', updated_by: adminId, updated_at: now() },
    { key: 'realtime_enabled', value: 'true', description: 'Enables WebSocket live updates.', updated_by: adminId, updated_at: now() }
  ]);

  // ---- Import / export ----
  const exportContent = JSON.stringify({
    meta: { format: 'p05-export', version: 1, exportedAt: now() },
    categories: db.prepare('SELECT name, slug, description FROM categories').all(),
    templates: db.prepare('SELECT name, slug, description, body FROM page_templates').all(),
    navMenus: db.prepare('SELECT label, url, position FROM nav_menus').all(),
    articles: db.prepare('SELECT title, slug, body, body_html, tags, status, publish_at, published_at, author_id, category_id, template_id FROM articles').all(),
    comments: db.prepare('SELECT article_id, author_name, author_email, body, status FROM comments').all(),
    settings: db.prepare('SELECT key, value FROM plugin_settings_panel').all()
  }, null, 2);
  const exportName = 'seed-export.json';
  fs.writeFileSync(path.join(env.exportDir, exportName), exportContent);
  const exportSfId = insert(db, `INSERT INTO stored_file (original_name, storage_name, mime_type, size_bytes, owner_id, kind, created_at)
    VALUES (@name, @name, 'application/json', @size, @owner, 'export', @now)`, {
    name: exportName,
    size: Buffer.byteLength(exportContent),
    owner: adminId,
    now: now()
  });
  insert(db, `INSERT INTO import_export (kind, format, status, file_id, record_count, scope, requested_by, created_at)
    VALUES ('export', 'json', 'completed', @fileId, @count, 'full', @requestedBy, @now)`, {
    fileId: exportSfId,
    count: db.prepare('SELECT COUNT(*) AS c FROM articles').get().c,
    requestedBy: adminId,
    now: now()
  });

  // ---- Frontend API integration and errors ----
  insertMany(db, 'frontend_api_integration_and_errors', [
    { kind: 'missing_page', status_code: 404, context: '/article/does-not-exist', message: 'Article not found', url: '/article/does-not-exist', user_id: users.visitor, resolved: 1, created_at: now() },
    { kind: 'validation', status_code: 400, context: 'comment form', message: 'Comment body is required.', url: '/article/welcome-to-p05-content-studio', user_id: users.visitor, resolved: 0, created_at: now() },
    { kind: 'preview', status_code: 200, context: 'rich text preview', message: 'Preview rendered successfully', url: '/content/edit/1', user_id: users.author, resolved: 0, created_at: now() }
  ]);

  // ---- Audit events ----
  insertMany(db, 'audit_events', [
    { actor_id: adminId, action: 'seed', entity_type: 'users', entity_id: users.author, details: 'Seeded user accounts and demo content', created_at: now() },
    { actor_id: adminId, action: 'role_change', entity_type: 'users', entity_id: users.moderator, details: 'Granted moderator role', created_at: now() },
    { actor_id: adminId, action: 'export', entity_type: 'import_export', entity_id: 1, details: 'Created seed export', created_at: now() }
  ]);

  console.log('[seed] Database seeded with roles, users, categories, templates, articles, media, comments, settings and audit events.');
  return { skipped: false };
}
