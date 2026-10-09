<?php $page_title = $page_title ?? 'Error'; ?>
<section>
  <h1>Something went wrong</h1>
  <p class="flash flash-error"><?= htmlspecialchars($error_message ?? 'Unknown error.', ENT_QUOTES, 'UTF-8') ?></p>
  <p><a href="/">Back to home</a></p>
</section>