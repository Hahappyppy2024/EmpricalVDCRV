<?php
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
?>
<h1>Welcome, <?= htmlspecialchars($user['display_name']) ?></h1>

<?php if ($role === 'student' || $role === 'visitor'): ?>
  <?php $enrolled = $enrolled_courses ?? []; ?>
  <h2>My courses (<?= count($enrolled) ?>)</h2>
  <?php if ($enrolled === []): ?>
    <p class="empty">You are not enrolled in any courses yet. <a href="/courses">Browse the catalogue</a>.</p>
  <?php else: ?>
    <div class="card-grid">
      <?php foreach ($enrolled as $course): ?>
        <a class="card" href="/courses/<?= (int) $course['course_id'] ?>">
          <span class="badge"><?= htmlspecialchars($course['category']) ?></span>
          <h3><?= htmlspecialchars($course['course_title']) ?></h3>
          <p class="muted"><?= htmlspecialchars($course['semester']) ?> &middot; <?= htmlspecialchars($course['instructor_name']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2>Latest announcements</h2>
  <?php $announcements = $announcements ?? []; ?>
  <?php if ($announcements === []): ?>
    <p class="empty">No announcements yet.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($announcements as $a): ?>
        <li><strong><?= htmlspecialchars($a['title']) ?></strong> &mdash; <?= htmlspecialchars($a['course_title']) ?> &middot; <span class="muted"><?= htmlspecialchars($a['published_at']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php elseif ($role === 'instructor'): ?>
  <?php $courses = $courses ?? []; ?>
  <h2>My courses (<?= count($courses) ?>)</h2>
  <?php if ($courses === []): ?>
    <p class="empty">You have no courses yet. Create one from the course catalogue.</p>
  <?php else: ?>
    <div class="card-grid">
      <?php foreach ($courses as $course): ?>
        <a class="card" href="/courses/<?= (int) $course['id'] ?>">
          <span class="badge"><?= htmlspecialchars($course['category']) ?></span>
          <h3><?= htmlspecialchars($course['title']) ?></h3>
          <p class="muted"><?= htmlspecialchars($course['semester']) ?> &middot; <?= (int) $course['enrollment_count'] ?> enrolled</p>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="muted">Pending submissions to review: <strong><?= (int) ($pending_submissions ?? 0) ?></strong></p>
<?php else: ?>
  <?php $stats = $stats ?? []; ?>
  <h2>Platform snapshot</h2>
  <div class="stat-grid">
    <div class="stat"><strong><?= (int) ($stats['total_courses'] ?? 0) ?></strong><span>Courses</span></div>
    <div class="stat"><strong><?= (int) ($stats['total_users'] ?? 0) ?></strong><span>Users</span></div>
    <div class="stat"><strong><?= (int) ($stats['total_enrollments'] ?? 0) ?></strong><span>Enrollments</span></div>
    <div class="stat"><strong><?= (int) ($stats['total_materials'] ?? 0) ?></strong><span>Materials</span></div>
  </div>
  <h2>Recent audit events</h2>
  <table class="table">
    <thead><tr><th>When</th><th>User</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach (($recent_audit ?? []) as $e): ?>
        <tr><td><?= htmlspecialchars($e['created_at']) ?></td><td><?= htmlspecialchars($e['username']) ?></td><td><?= htmlspecialchars($e['action']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
