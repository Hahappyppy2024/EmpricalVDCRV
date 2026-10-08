<?php
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
$isManager = $role === 'admin' || ($role === 'instructor' && (int) $course['instructor_id'] === (int) $user['id']);
?>
<nav class="tabs">
  <a href="/courses/<?= (int) $course['id'] ?>" class="active">Materials &amp; Announcements</a>
  <a href="/courses/<?= (int) $course['id'] ?>/discussion">Discussion</a>
  <a href="/courses/<?= (int) $course['id'] ?>/assignments">Assignments</a>
  <a href="/courses/<?= (int) $course['id'] ?>/quizzes">Quizzes</a>
  <?php if ($isManager): ?>
    <a href="/courses/<?= (int) $course['id'] ?>/gradebook">Gradebook</a>
  <?php endif; ?>
</nav>

<h1><?= htmlspecialchars($course['title']) ?></h1>
<p class="muted"><?= htmlspecialchars($course['category']) ?> &middot; <?= htmlspecialchars($course['semester']) ?> &middot; <?= htmlspecialchars($course['instructor_name']) ?> &middot; <span class="<?= $course['status'] === 'open' ? 'ok' : 'muted' ?>"><?= htmlspecialchars($course['status']) ?></span></p>
<p><?= htmlspecialchars($course['description']) ?></p>

<?php if ($isManager): ?>
<details class="panel">
  <summary>Upload course material</summary>
  <form id="material-form" enctype="multipart/form-data">
    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
    <label>Title <input type="text" name="title" class="input" required></label>
    <label>Description <textarea name="description" class="input"></textarea></label>
    <label>File <input type="file" name="file" class="input" required></label>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
  <div id="material-msg"></div>
</details>
<?php endif; ?>

<div class="grid-2">
  <section>
    <h2>Materials</h2>
    <div id="materials"><div class="empty">Loading...</div></div>
  </section>
  <section>
    <h2>Announcements</h2>
    <?php if ($isManager): ?>
    <details class="panel">
      <summary>Post announcement</summary>
      <form id="announcement-form">
        <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
        <label>Title <input type="text" name="title" class="input" required></label>
        <label>Body <textarea name="body" class="input"></textarea></label>
        <button type="submit" class="btn btn-primary">Publish</button>
      </form>
    </details>
    <?php endif; ?>
    <div id="announcements"><div class="empty">Loading...</div></div>
  </section>
</div>

<script>
(function () {
  var cid = <?= (int) $course['id'] ?>;
  var isManager = <?= $isManager ? 'true' : 'false' ?>;

  function loadMaterials() {
    LMS.load(document.getElementById('materials'), LMS.api('/api/lms/course_materials?course_id=' + cid), function (data) {
      var list = data.materials || [];
      if (!list.length) { document.getElementById('materials').innerHTML = '<div class="empty">No materials yet.</div>'; return; }
      document.getElementById('materials').innerHTML = list.map(function (m) {
        return '<div class="row"><div><strong>' + LMS.esc(m.title) + '</strong><br><span class="muted">' + LMS.esc(m.description || '') + ' &middot; ' + m.size_bytes + ' bytes</span></div><a class="btn" href="/download/material/' + m.id + '">Download</a></div>';
      }).join('');
    });
  }
  function loadAnnouncements() {
    LMS.load(document.getElementById('announcements'), LMS.api('/api/lms/announcements?course_id=' + cid), function (data) {
      var list = data.announcements || [];
      if (!list.length) { document.getElementById('announcements').innerHTML = '<div class="empty">No announcements yet.</div>'; return; }
      document.getElementById('announcements').innerHTML = list.map(function (a) {
        return '<div class="panel"><h3>' + LMS.esc(a.title) + '</h3><p>' + LMS.esc(a.body || '') + '</p><p class="muted">' + LMS.esc(a.published_at) + ' &middot; ' + LMS.esc(a.author_name) + '</p></div>';
      }).join('');
    });
  }

  var mf = document.getElementById('material-form');
  if (mf) mf.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/course_materials', { method: 'POST', body: new FormData(mf) }).then(function (r) {
      document.getElementById('material-msg').innerHTML = r.ok ? '<div class="alert alert-success">Material uploaded.</div>' : '<div class="alert alert-error">' + LMS.esc(r.error.message) + '</div>';
      if (r.ok) { mf.reset(); loadMaterials(); }
    });
  });
  var af = document.getElementById('announcement-form');
  if (af) af.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/announcements', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(af))) }).then(function (r) {
      if (r.ok) { af.reset(); loadAnnouncements(); } else { alert(r.error.message); }
    });
  });

  loadMaterials();
  loadAnnouncements();
  LMS.wsJoin(cid, function (event) {
    if (event.type === 'announcement') loadAnnouncements();
    if (event.type === 'discussion') console.log('New discussion activity');
  });
})();
</script>
