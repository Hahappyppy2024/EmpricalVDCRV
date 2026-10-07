<div class="card">
  <h2>Me</h2>
  <pre><?= h(json_encode($user, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre>
</div>
