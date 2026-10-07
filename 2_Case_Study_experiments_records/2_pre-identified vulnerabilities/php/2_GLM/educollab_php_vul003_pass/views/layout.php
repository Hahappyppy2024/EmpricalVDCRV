<?php
// $title, and $view (from render) are available
$user = $_SESSION['user'] ?? null;

?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($title ?? 'EduCollab v0') ?></title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:20px;}
    nav a{margin-right:10px;}
    .card{border:1px solid #ddd;border-radius:10px;padding:12px;margin:12px 0;}
    input,textarea,select{width:100%;padding:8px;margin:6px 0;}
    button{padding:8px 12px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border:1px solid #eee;padding:8px;text-align:left;}
    .muted{color:#666;}
  </style>
</head>
<body>
  <nav>
    <a href="/">Home</a>
    <?php if ($user): ?>
      <a href="/courses">Courses</a>
      <a href="/memberships">My Memberships</a>
      <a href="/my/submissions">My Submissions</a>
      <a href="/my/quizzes">My Quiz Attempts</a>
      <a href="/me">Me</a>
      <?php if (is_global_admin($user)): ?>
        <a href="/admin/users">Admin: Users</a>
      <?php endif; ?>
      <form style="display:inline" method="post" action="/logout"><button type="submit">Logout</button></form>
      <span class="muted">Logged in as <?= h($user['username'] ?? '') ?></span>
    <?php else: ?>
      <a href="/login">Login</a>
      <a href="/register">Register</a>
    <?php endif; ?>
  </nav>

  <?php if (!empty($error)): ?>
    <div class="card" style="border-color:#f99;">Error: <?= h($error) ?></div>
  <?php endif; ?>

  <?php include $viewFile; ?>
</body>
</html>
