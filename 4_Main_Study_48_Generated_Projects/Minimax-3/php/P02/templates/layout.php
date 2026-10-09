<?php
$navLinks = [];
if ($user) {
    $roles = $user['roles'];
    $navLinks[] = ['Dashboard', '/dashboard'];
    if (in_array('author', $roles, true) || in_array('reviewer', $roles, true) || in_array('chair', $roles, true)) {
        $navLinks[] = ['Discover', '/submission_discovery'];
    }
    if (in_array('author', $roles, true)) {
        $navLinks[] = ['Submit', '/paper_submission'];
        $navLinks[] = ['Rebuttal', '/rebuttal'];
    }
    if (in_array('reviewer', $roles, true)) {
        $navLinks[] = ['Reviewing', '/reviewing'];
    }
    if (in_array('chair', $roles, true) || in_array('admin', $roles, true)) {
        $navLinks[] = ['Phases', '/conference_phases'];
        $navLinks[] = ['Assignments', '/reviewer_assignment'];
        $navLinks[] = ['Decisions', '/decision_management'];
        $navLinks[] = ['Exports', '/bulk_exports'];
    }
    $navLinks[] = ['API Errors', '/frontend_api_integration_and_errors'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title ?? $appName) ?></title>
<link rel="stylesheet" href="/static/app.css">
</head>
<body>
<header class="topbar">
  <div class="container">
    <div class="brand">
      <a href="/"><?= htmlspecialchars($appName) ?></a>
    </div>
    <nav class="nav">
      <?php foreach ($navLinks as $l): ?>
        <a href="<?= $l[1] ?>"><?= htmlspecialchars($l[0]) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <span class="user-pill"><?= htmlspecialchars($user['display_name']) ?> (<?= htmlspecialchars(implode(',', $user['roles'])) ?>)</span>
        <form action="/logout" method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
          <button type="submit" class="btn btn-link">Sign out</button>
        </form>
      <?php else: ?>
        <a href="/login">Sign in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($flash): ?>
<div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>
<main class="container">
  <?= $body ?>
</main>
<footer class="footer">
  <div class="container">
    <small><?= htmlspecialchars($appName) ?> &mdash; PHP <?= PHP_VERSION ?> / Slim 4 &mdash; deterministic synthetic benchmark</small>
  </div>
</footer>
<script src="/static/app.js"></script>
</body>
</html>