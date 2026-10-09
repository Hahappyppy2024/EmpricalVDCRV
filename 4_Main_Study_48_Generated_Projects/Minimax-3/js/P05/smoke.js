// Smoke test - runs all 12 use case endpoints and reports status codes.
import http from 'node:http';

const BASE = 'http://localhost:3000';

function req(method, path, { body, cookie, headers = {} } = {}) {
  return new Promise((resolve, reject) => {
    const data = body ? Buffer.from(JSON.stringify(body)) : null;
    const opts = {
      method,
      headers: {
        ...headers,
        ...(cookie ? { Cookie: cookie } : {}),
        ...(data ? { 'Content-Type': 'application/json', 'Content-Length': data.length } : {})
      }
    };
    const r = http.request(BASE + path, opts, (res) => {
      let chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve({
        status: res.statusCode,
        headers: res.headers,
        body: Buffer.concat(chunks).toString('utf8')
      }));
    });
    r.on('error', reject);
    if (data) r.write(data);
    r.end();
  });
}

function extractCookie(headers) {
  const sc = headers['set-cookie'];
  if (!sc) return null;
  return sc.map(s => s.split(';')[0]).join('; ');
}

const results = [];
function check(name, ok, info = '') {
  results.push({ name, ok, info });
  console.log(`${ok ? '✓' : '✗'} ${name}${info ? ' — ' + info : ''}`);
}

(async () => {
  // 1. Anonymous public listing
  let r = await req('GET', '/api/cms/public_site');
  let data = JSON.parse(r.body);
  check('CMS-06 public listing anonymous', data.articles?.length >= 2, `${data.articles?.length} articles`);

  // 2. Login as admin
  r = await req('POST', '/api/cms/account_access?action=login', { body: { username: 'admin', password: 'admin123' } });
  let cookie = extractCookie(r.headers);
  let loginData = JSON.parse(r.body);
  check('CMS-01 login admin', r.status === 200 && loginData.user?.role === 'admin');

  // 3. Account info
  r = await req('GET', '/api/cms/account_access', { cookie });
  check('CMS-01 account info', JSON.parse(r.body).user?.username === 'admin');

  // 4. List articles
  r = await req('GET', '/api/cms/content_authoring', { cookie });
  check('CMS-02 list articles', JSON.parse(r.body).articles?.length >= 4);

  // 5. Create article
  r = await req('POST', '/api/cms/content_authoring', {
    cookie,
    body: { title: 'Smoke article ' + Date.now(), summary: 'x', body: '<p>x</p>', tags: ['smoke'] }
  });
  check('CMS-02 create article', r.status === 201);
  const articleId = JSON.parse(r.body).article?.id;

  // 6. Update article
  r = await req('PATCH', `/api/cms/content_authoring/${articleId}`, {
    cookie, body: { summary: 'updated summary' }
  });
  check('CMS-02 update article', r.status === 200);

  // 7. Rich text
  r = await req('POST', '/api/cms/rich_text_editor', {
    cookie, body: { articleId, bodyHtml: '<p>Hello <strong>world</strong></p>' }
  });
  check('CMS-03 save rich text', r.status === 201);

  // 8. Media list
  r = await req('GET', '/api/cms/media_library', { cookie });
  check('CMS-04 list media', Array.isArray(JSON.parse(r.body).items));

  // 9. Publishing workflow
  r = await req('GET', '/api/cms/publishing_workflow', { cookie });
  check('CMS-05 list workflow', Array.isArray(JSON.parse(r.body).items));

  // 10. Publish article
  r = await req('POST', '/api/cms/publishing_workflow', {
    cookie, body: { articleId, action: 'review' }
  });
  check('CMS-05 draft->review', r.status === 201);
  r = await req('POST', '/api/cms/publishing_workflow', {
    cookie, body: { articleId, action: 'published' }
  });
  check('CMS-05 review->published', r.status === 201);

  // 11. Public page now visible
  // The article slug will be smoke-article-<ts>; let's find the slug
  r = await req('GET', '/api/cms/content_authoring', { cookie });
  const slug = JSON.parse(r.body).articles.find(a => a.id === articleId)?.slug;
  r = await req('GET', `/api/cms/public_site/page/${slug}`);
  check('CMS-06 published page visible', r.status === 200 && JSON.parse(r.body).page?.title);

  // 12. Public search
  r = await req('POST', '/api/cms/public_site', { body: { q: 'smoke' } });
  check('CMS-06 public search', Array.isArray(JSON.parse(r.body).results));

  // 13. Comment anonymously
  r = await req('POST', '/api/cms/comments', {
    body: { articleId: 1, authorName: 'Anon', body: 'smoke test comment' }
  });
  check('CMS-07 anonymous comment', r.status === 201);
  const commentId = JSON.parse(r.body).comment?.id;

  // 14. Login as moderator
  r = await req('POST', '/api/cms/account_access?action=login', { body: { username: 'mod1', password: 'moderator123' } });
  const modCookie = extractCookie(r.headers);
  check('CMS-01 login moderator', r.status === 200);

  // 15. Approve comment
  r = await req('PATCH', `/api/cms/comments/${commentId}`, {
    cookie: modCookie, body: { state: 'approved' }
  });
  check('CMS-07 approve comment', r.status === 200);

  // 16. Templates + menus
  r = await req('GET', '/api/cms/page_templates', { cookie });
  const tplData = JSON.parse(r.body);
  check('CMS-08 list templates', tplData.templates?.length >= 3);

  // 17. Create template
  r = await req('POST', '/api/cms/page_templates', {
    cookie, body: { name: 'Smoke ' + Date.now(), layoutHtml: '<x/>', regions: ['main'] }
  });
  check('CMS-08 create template', r.status === 201);

  // 18. Users list
  r = await req('GET', '/api/cms/user_and_role_management', { cookie });
  const u = JSON.parse(r.body);
  check('CMS-09 list users', u.users?.length >= 6);

  // 19. Assign role
  r = await req('POST', '/api/cms/user_and_role_management', {
    cookie, body: { action: 'assign_role', userId: 6, roleName: 'visitor' }
  });
  check('CMS-09 assign role', r.status === 200, r.body);

  // 20. Plugin settings list
  r = await req('GET', '/api/cms/plugin_settings_panel', { cookie });
  check('CMS-10 list settings', JSON.parse(r.body).settings?.length >= 12);

  // 21. Update setting
  r = await req('PATCH', '/api/cms/plugin_settings_panel/1', {
    cookie, body: { value: 'Updated title ' + Date.now() }
  });
  check('CMS-10 update setting', r.status === 200);

  // 22. Export
  r = await req('POST', '/api/cms/import_export', {
    cookie, body: { jobType: 'export' }
  });
  check('CMS-11 export', r.status === 201);

  // 23. Frontend API integration log
  r = await req('POST', '/api/cms/frontend_api_integration_and_errors', {
    cookie, body: { endpoint: '/api/cms/public_site', method: 'GET' }
  });
  check('CMS-12 log integration', r.status === 200);

  // 24. Frontend simulate validation error
  r = await req('POST', '/api/cms/frontend_api_integration_and_errors', {
    cookie, body: { endpoint: '/api/cms/public_site', method: 'GET', simulate: 'validation' }
  });
  check('CMS-12 simulate validation (422)', r.status === 422);

  // 25. Logout
  r = await req('POST', '/api/cms/account_access/logout', { cookie });
  check('CMS-01 logout', r.status === 200);

  // 26. After logout, protected route fails
  r = await req('GET', '/api/cms/content_authoring', { cookie, headers: { Accept: 'application/json' } });
  check('CMS-01 protected after logout (401)', r.status === 401);

  // 27. Static files served
  r = await req('GET', '/css/styles.css');
  check('Static CSS served', r.status === 200);

  // 28. Login as visitor (cannot access admin endpoints)
  r = await req('POST', '/api/cms/account_access?action=login', { body: { username: 'visitor1', password: 'visitor123' } });
  const visCookie = extractCookie(r.headers);
  r = await req('GET', '/api/cms/user_and_role_management', { cookie: visCookie, headers: { Accept: 'application/json' } });
  check('CMS-09 visitor denied (403)', r.status === 403);

  // 29. Invalid transition: re-login then try invalid skip
  r = await req('POST', '/api/cms/account_access?action=login', { body: { username: 'admin', password: 'admin123' } });
  const adminCookie2 = extractCookie(r.headers);
  // pick a draft article if exists, otherwise create
  r = await req('GET', '/api/cms/content_authoring', { cookie: adminCookie2 });
  const drafts = JSON.parse(r.body).articles.filter(a => a.status === 'draft');
  if (drafts.length === 0) {
    r = await req('POST', '/api/cms/content_authoring', {
      cookie: adminCookie2, body: { title: 'Invalid transition test ' + Date.now() }
    });
    drafts.push(JSON.parse(r.body).article);
  }
  r = await req('POST', '/api/cms/publishing_workflow', {
    cookie: adminCookie2, body: { articleId: drafts[0].id, action: 'published' }
  });
  check('Invalid transition (draft->published rejected)', r.status === 409, `status=${r.status} body=${r.body.slice(0,100)}`);

  console.log('\n=== Summary ===');
  const passed = results.filter(r => r.ok).length;
  const failed = results.filter(r => !r.ok).length;
  console.log(`Passed: ${passed} / ${results.length}, Failed: ${failed}`);
  process.exit(failed > 0 ? 1 : 0);
})();
