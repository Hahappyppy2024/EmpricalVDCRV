<?php
namespace App\Views;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> Â· <?= e($app_name) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="page">
<header class="topbar">
  <div class="brand"><?= e($app_name) ?></div>
  <nav class="nav">
    <a class="nav__link <?= $active === 'pages/dashboard' ? 'is-active' : '' ?>" href="/dashboard">Dashboard</a>
    <a class="nav__link <?= $active === 'pages/paper_submission' ? 'is-active' : '' ?>" href="/papers">Papers</a>
    <a class="nav__link <?= $active === 'pages/submission_discovery' ? 'is-active' : '' ?>" href="/discovery">Discovery</a>
    <?php if (in_array($user['role'], ['reviewer', 'chair', 'admin'], true)): ?>
      <a class="nav__link <?= $active === 'pages/reviewing' ? 'is-active' : '' ?>" href="/reviewer">Reviewing</a>
    <?php endif; ?>
    <a class="nav__link <?= $active === 'pages/rebuttal' ? 'is-active' : '' ?>" href="/rebuttal">Rebuttal</a>
    <?php if (in_array($user['role'], ['chair', 'admin'], true)): ?>
      <a class="nav__link <?= str_starts_with($active, 'pages/chair') || $active === 'pages/reviewer_assignment' || $active === 'pages/conference_phases' || $active === 'pages/decision_management' || $active === 'pages/bulk_exports' ? 'is-active' : '' ?>" href="/chair">Chair</a>
    <?php endif; ?>
    <a class="nav__link <?= $active === 'pages/double_blind_views' ? 'is-active' : '' ?>" href="/blind-views">Blind views</a>
    <a class="nav__link <?= $active === 'pages/account_access' ? 'is-active' : '' ?>" href="/account">Account</a>
    <form class="nav__form" data-api-form method="post" action="/auth/logout" data-redirect="/login">
      <button class="btn btn--ghost" type="submit">Sign out (<?= e($user['name']) ?>)</button>
    </form>
  </nav>
</header>
<main class="content">
<?= $content ?>
</main>
<footer class="footer"><?= e($app_name) ?> Â· PHP 8.3 Â· Slim 4 Â· SQLite</footer>
<div id="toast" class="toast" hidden></div>
<script>
  window.CONF = {
    ws_url: <?= json_encode($ws_url) ?>,
    app_url: <?= json_encode($app_url) ?>,
    user: <?= json_encode($user) ?>
  };
</script>
<script src="/assets/js/app.js"></script>
</body>
</html>