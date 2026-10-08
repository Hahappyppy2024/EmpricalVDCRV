<?php
/** @var array<string, mixed> $course */
?>
<nav class="tabs">
  <a href="/courses/<?= (int) $course['id'] ?>">Materials &amp; Announcements</a>
  <a href="/courses/<?= (int) $course['id'] ?>/discussion" class="active">Discussion</a>
  <a href="/courses/<?= (int) $course['id'] ?>/assignments">Assignments</a>
  <a href="/courses/<?= (int) $course['id'] ?>/quizzes">Quizzes</a>
</nav>
<h1>Discussion Board &mdash; <?= htmlspecialchars($course['title']) ?></h1>
<p class="muted">Real-time via local WebSocket (Workerman). The board falls back to HTTP refresh if the socket is unavailable.</p>

<details class="panel">
  <summary>Start a new thread</summary>
  <form id="thread-form">
    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
    <label>Subject <input type="text" name="subject" class="input" required></label>
    <label>Body <textarea name="body" class="input"></textarea></label>
    <button type="submit" class="btn btn-primary">Post</button>
  </form>
</details>

<form class="filters" id="search-form">
  <input type="search" name="q" placeholder="Search threads..." class="input">
  <button type="submit" class="btn">Search</button>
</form>

<div id="threads"><div class="empty">Loading...</div></div>

<script>
(function () {
  var cid = <?= (int) $course['id'] ?>;
  var container = document.getElementById('threads');
  var cidVar = 'cid=' + cid;

  function render(data) {
    var threads = data.threads || [];
    if (!threads.length) { container.innerHTML = '<div class="empty">No discussion threads yet. Start one above.</div>'; return; }
    container.innerHTML = threads.map(function (t) {
      return '<div class="panel">' +
        '<h3><a href="#" data-thread="' + t.id + '" class="thread-link">' + LMS.esc(t.subject) + '</a></h3>' +
        '<p>' + LMS.esc(t.body || '') + '</p>' +
        '<p class="muted">' + LMS.esc(t.author_name) + ' &middot; ' + LMS.esc(t.created_at) + ' &middot; ' + (t.reply_count || 0) + ' replies</p>' +
        '<div class="thread-replies" id="replies-' + t.id + '" hidden></div>' +
        '</div>';
    }).join('');

    container.querySelectorAll('.thread-link').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        toggleReplies(a, parseInt(a.dataset.thread, 10));
      });
    });
  }

  function toggleReplies(link, threadId) {
    var box = document.getElementById('replies-' + threadId);
    if (!box.hidden) { box.hidden = true; return; }
    box.hidden = false;
    box.innerHTML = '<div class="empty">Loading replies...</div>';
    LMS.api('/api/lms/discussion_board?' + cidVar + '&thread_id=' + threadId).then(function (r) {
      if (!r.ok) { box.innerHTML = '<div class="empty">' + LMS.esc(r.error.message) + '</div>'; return; }
      var replies = (r.data.replies || []);
      box.innerHTML = (replies.length ? replies.map(function (p) {
        return '<div class="row"><div><strong>' + LMS.esc(p.author_name) + '</strong> &middot; <span class="muted">' + LMS.esc(p.created_at) + '</span><br>' + LMS.esc(p.body || '') + '</div></div>';
      }).join('') : '<div class="empty">No replies yet.</div>') +
        '<form class="reply-form"><input type="hidden" name="parent_id" value="' + threadId + '">' +
        '<input type="hidden" name="course_id" value="' + cid + '">' +
        '<label>Reply <textarea name="body" class="input" required></textarea></label>' +
        '<input type="hidden" name="subject" value="Re: thread">' +
        '<button type="submit" class="btn">Reply</button></form>';
      box.querySelector('.reply-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        LMS.api('/api/lms/discussion_board', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(ev.target))) }).then(function (rr) {
          if (rr.ok) toggleReplies(link, threadId); else alert(rr.error.message);
        });
      });
    });
  }

  document.getElementById('thread-form').addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/discussion_board', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(e.target))) }).then(function (r) {
      if (r.ok) { e.target.reset(); load(); } else { alert(r.error.message); }
    });
  });

  var sf = document.getElementById('search-form');
  sf.addEventListener('submit', function (e) {
    e.preventDefault();
    load('&q=' + encodeURIComponent(sf.elements.q.value));
  });

  function load(extra) {
    LMS.load(container, LMS.api('/api/lms/discussion_board?' + cidVar + (extra || '')), render);
  }
  load();
  LMS.wsJoin(cid, function (event) { if (event.type === 'discussion') load(); });
})();
</script>
