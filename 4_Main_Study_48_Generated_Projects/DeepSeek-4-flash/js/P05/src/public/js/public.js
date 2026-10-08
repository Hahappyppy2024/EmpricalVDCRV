import { api } from './api.js';
import { escapeHtml, formatDate, shortDate, tagChips } from './util.js';

function setNav(nav) {
  const navEl = document.getElementById('nav-menus');
  if (!navEl) return;
  navEl.innerHTML = (nav || []).map((item) => `<a href="${escapeHtml(item.url)}">${escapeHtml(item.label)}</a>`).join(' ');
}

function setHero(site) {
  const title = document.getElementById('hero-title');
  const tagline = document.getElementById('hero-tagline');
  if (title) title.textContent = site.site_name || 'P05 Content Studio';
  if (tagline) tagline.textContent = site.site_tagline || '';
}

function renderListing(articles) {
  const grid = document.getElementById('article-grid');
  if (!grid) return;
  if (!articles || articles.length === 0) {
    grid.innerHTML = '<div class="empty">No published articles found.</div>';
    return;
  }
  grid.innerHTML = articles.map((a) => `
    <article class="article-card">
      <div class="card-body">
        <h3><a href="/article/${escapeHtml(a.slug)}">${escapeHtml(a.title)}</a></h3>
        <div class="card-meta">
          ${a.category_name ? `<a href="/category/${escapeHtml(a.category_slug)}">${escapeHtml(a.category_name)}</a> · ` : ''}
          by ${escapeHtml(a.author_display_name || a.author_username || 'unknown')} · ${shortDate(a.published_at)}
        </div>
        ${a.featured_media_url ? `<img src="${escapeHtml(a.featured_media_url)}" alt="${escapeHtml(a.featured_media_alt || '')}" style="width:100%; max-height:180px; object-fit:cover; border-radius:6px; margin-bottom:10px;">` : ''}
        <div class="card-excerpt">${a.excerpt || ''}</div>
        <div style="margin-top:10px;">${tagChips(a.tags)}</div>
      </div>
    </article>
  `).join('');
}

function excerptOf(html, len = 160) {
  const div = document.createElement('div');
  div.innerHTML = html || '';
  const text = (div.textContent || '').trim().replace(/\s+/g, ' ');
  return escapeHtml(text.length > len ? text.slice(0, len) + '…' : text);
}

async function initListing() {
  const path = window.location.pathname;
  const params = new URLSearchParams(window.location.search);
  const listingTitle = document.getElementById('listing-title');
  const categoriesEl = document.getElementById('categories');

  const settings = await api('/api/public/settings');
  setNav(settings.data.nav);
  setHero(settings.data.site);

  if (categoriesEl) {
    const cats = await api('/api/public/categories');
    categoriesEl.innerHTML = `<div style="margin-bottom:6px;">
      ${cats.data.map((c) => `<a class="tag" href="/category/${escapeHtml(c.slug)}">${escapeHtml(c.name)} (${c.article_count})</a>`).join(' ')}
    </div>`;
  }

  let query = '';
  if (path.startsWith('/category/')) {
    query = `category=${encodeURIComponent(path.slice('/category/'.length))}`;
    if (listingTitle) listingTitle.textContent = 'Category articles';
  } else if (path === '/search') {
    const q = params.get('q') || '';
    query = `q=${encodeURIComponent(q)}`;
    if (listingTitle) listingTitle.textContent = q ? `Search results for “${q}”` : 'All articles';
  } else {
    if (listingTitle) listingTitle.textContent = 'Latest articles';
  }

  const res = await api(`/api/public/articles${query ? `?${query}` : ''}`);
  const withExcerpt = res.data.map((a) => ({ ...a, excerpt: excerptOf(a.body_html) }));
  renderListing(withExcerpt);
}

async function initArticle() {
  const slug = window.location.pathname.split('/').pop();
  const root = document.getElementById('article-root');
  if (!root) return;

  const settings = await api('/api/public/settings');
  setNav(settings.data.nav);

  let article;
  try {
    const res = await api(`/api/public/articles/${encodeURIComponent(slug)}`);
    article = res.data;
  } catch (err) {
    root.innerHTML = `
      <div class="form-card" style="text-align:center;">
        <h1>404</h1>
        <p>This article does not exist or is not published.</p>
        <a class="btn" href="/">Back to home</a>
      </div>`;
    return;
  }

  root.innerHTML = `
    <a href="/" style="font-size:0.9rem;">← Home</a>
    <article class="card" style="margin-top:12px;">
      <h1 style="margin-top:0;">${escapeHtml(article.title)}</h1>
      <div class="card-meta">
        by ${escapeHtml(article.author_display_name || article.author_username || 'unknown')} ·
        ${article.category_name ? `<a href="/category/${escapeHtml(article.category_slug)}">${escapeHtml(article.category_name)}</a> · ` : ''}
        published ${formatDate(article.published_at)}
      </div>
      ${article.featured_media_url ? `<img src="${escapeHtml(article.featured_media_url)}" alt="${escapeHtml(article.featured_media_alt || '')}" style="width:100%; max-height:360px; object-fit:cover; border-radius:8px; margin-bottom:16px;">` : ''}
      <div class="article-body">${article.body_html || '<p>' + escapeHtml(article.body) + '</p>'}</div>
      <div style="margin-top:14px;">${tagChips(article.tags)}</div>
    </article>

    <section class="comments-section card">
      <h2>Comments (${article.approved_comments || 0})</h2>
      <div id="comment-list"><div class="empty">Loading…</div></div>
      <div id="comment-form-wrap"></div>
    </section>
  `;

  // Record a public site view event
  try {
    await api('/api/cms/public_site', {
      method: 'POST',
      body: { articleId: article.id, visitorName: null, action: 'view', referrer: document.referrer }
    });
  } catch { /* non-fatal */ }

  await renderComments(article.id);
}

async function renderComments(articleId) {
  const list = document.getElementById('comment-list');
  const formWrap = document.getElementById('comment-form-wrap');
  if (!list) return;

  const res = await api(`/api/cms/comments?articleId=${articleId}`);
  const comments = res.data || [];
  list.innerHTML = comments.length === 0
    ? '<div class="empty">No comments yet. Be the first to comment!</div>'
    : comments.map((c) => `
      <div class="comment">
        <div class="comment-meta"><strong>${escapeHtml(c.author_name)}</strong> · ${formatDate(c.created_at)}</div>
        <div>${escapeHtml(c.body)}</div>
      </div>
    `).join('');

  const allowComments = res && res.data && comments ? true : true;
  if (!allowComments) return;

  formWrap.innerHTML = `
    <form id="comment-form">
      <div class="field">
        <label for="comment-name">Name</label>
        <input id="comment-name" required maxlength="80" placeholder="Your name">
      </div>
      <div class="field">
        <label for="comment-email">Email <span class="hint">(optional, never shown)</span></label>
        <input id="comment-email" type="email" maxlength="120" placeholder="you@example.com">
      </div>
      <div class="field">
        <label for="comment-body">Comment</label>
        <textarea id="comment-body" required maxlength="4000" rows="3" placeholder="Write a comment…"></textarea>
      </div>
      <button class="btn" type="submit">Post comment</button>
    </form>`;

  formWrap.querySelector('#comment-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const result = await api('/api/cms/comments', {
        method: 'POST',
        body: {
          articleId,
          authorName: document.getElementById('comment-name').value,
          authorEmail: document.getElementById('comment-email').value || null,
          body: document.getElementById('comment-body').value
        }
      });
      document.getElementById('comment-body').value = '';
      renderComments(articleId);
      const banner = document.createElement('div');
      banner.style.cssText = 'background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:12px;';
      banner.textContent = result.message || 'Comment posted.';
      formWrap.prepend(banner);
      setTimeout(() => banner.remove(), 4000);
    } catch (err) {
      const banner = document.createElement('div');
      banner.style.cssText = 'background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:12px;';
      banner.textContent = err.message;
      formWrap.prepend(banner);
      setTimeout(() => banner.remove(), 5000);
    }
  });
}

const path = window.location.pathname;
if (path.startsWith('/article/')) {
  initArticle();
} else {
  initListing();
}
