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
<body class="page page--auth">
<header class="topbar">
  <div class="brand"><?= e($app_name) ?></div>
  <nav class="nav">
    <a class="nav__link" href="/login">Sign in</a>
    <a class="nav__link" href="/register">Create account</a>
  </nav>
</header>
<main class="content content--narrow">
<?= $content ?>
</main>
<div id="toast" class="toast" hidden></div>
<script>
  window.CONF = { ws_url: <?= json_encode($ws_url) ?>, user: null };
</script>
<script src="/assets/js/app.js"></script>
</body>
</html>