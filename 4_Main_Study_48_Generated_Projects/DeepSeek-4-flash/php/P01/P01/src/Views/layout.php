<?php
/** @var \App\Config $config */
/** @var array<string, mixed>|null $user */
/** @var string $title */
/** @var string $content */
/** @var string $active */
$role = $user['role_name'] ?? 'visitor';
if (!function_exists('nav_item')) {
    function nav_item(string $href, string $label, string $active, string $key): string {
        $class = $active === $key ? ' class="active"' : '';
        return '<a href="' . htmlspecialchars($href) . '"' . $class . '>' . htmlspecialchars($label) . '</a>';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> &middot; <?= htmlspecialchars($config->siteName()) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="/dashboard"><?= htmlspecialchars($config->siteName()) ?></a>
    <?php if ($user !== null): ?>
    <nav class="nav">
      <?= nav_item('/dashboard', 'Dashboard', $active, 'dashboard') ?>
      <?= nav_item('/courses', 'Courses', $active, 'courses') ?>
      <?php if (in_array($role, ['instructor', 'admin'], true)): ?>
        <?= nav_item('/exports', 'Exports', $active, 'exports') ?>
      <?php endif; ?>
      <?php if ($role === 'student' || $role === 'visitor'): ?>
        <?= nav_item('/grades', 'My Grades', $active, 'grades') ?>
      <?php endif; ?>
      <?php if ($role === 'admin'): ?>
        <?= nav_item('/admin', 'Admin', $active, 'admin') ?>
        <?= nav_item('/admin/reports', 'Reports', $active, 'reports') ?>
      <?php endif; ?>
      <?= nav_item('/api-integration', 'API Demo', $active, 'api') ?>
      <span class="user-chip"><?= htmlspecialchars($user['display_name']) ?> (<?= htmlspecialchars($role) ?>)</span>
      <form method="post" action="/logout" class="inline-form"><button type="submit" class="link-btn">Sign out</button></form>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="container page"><?= $content ?></main>
<footer class="footer">
  <div class="container">
    <p><?= htmlspecialchars($config->siteName()) ?> &middot; P01 synthetic benchmark project &middot; PHP <?= PHP_VERSION ?></p>
  </div>
</footer>
<script src="/assets/js/app.js"></script>
<?php if ($user !== null): ?>
<script>
window.LMS = {
  wsUrl: 'ws://' + '<?= htmlspecialchars($config->wsHost() . ':' . $config->wsPort()) ?>',
  me: {
    id: <?= (int) $user['id'] ?>,
    username: <?= json_encode($user['username']) ?>,
    display_name: <?= json_encode($user['display_name']) ?>,
    role: <?= json_encode($role) ?>
  }
};
</script>
<?php endif; ?>
</body>
</html>
