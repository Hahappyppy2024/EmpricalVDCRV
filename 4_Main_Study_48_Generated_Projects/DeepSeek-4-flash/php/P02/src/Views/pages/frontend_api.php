<?php
namespace App\Views;
?>
<?php $catalog = $result['catalog'] ?? []; $reports = $result['reports'] ?? []; ?>
<h1>Frontend API integration and errors</h1>
<p class="muted">The browser client presents validation, permission, and phase errors through a single predictable envelope:
<code>{"ok":false,"error":{code,status,message,details}}</code>.</p>

<div class="card">
  <h2>Error catalog</h2>
  <table class="table">
    <thead><tr><th>Code</th><th>HTTP</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($catalog as $entry): ?>
      <tr>
        <td><code><?= e($entry['code']) ?></code></td>
        <td><?= (int) $entry['status'] ?></td>
        <td><?= e($entry['message']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="grid grid--2">
  <section class="card">
    <h2>Trigger a reported error</h2>
    <form data-api-form data-json="true" method="post" action="/api/conf/frontend_api_integration_and_errors" data-refresh="true" class="stack">
      <label>Endpoint
        <input type="text" name="endpoint" value="/api/conf/paper_submission" required>
      </label>
      <label>Error code
        <select name="error_code">
          <option value="validation_error">validation_error</option>
          <option value="permission_error">permission_error</option>
          <option value="phase_error">phase_error</option>
          <option value="not_found">not_found</option>
          <option value="conflict_error">conflict_error</option>
        </select>
      </label>
      <label>Error message
        <input type="text" name="error_message" required>
      </label>
      <button class="btn btn--primary" type="submit">Report error</button>
    </form>
  </section>

  <section class="card">
    <h2>Demo invalid request</h2>
    <p class="muted">Submitting this form sends an invalid paper submission; the UI renders the validation error banner predictably.</p>
    <form data-api-form method="post" action="/api/conf/paper_submission" class="stack">
      <label>Title <input type="text" name="title"></label>
      <label>Abstract <textarea name="abstract"></textarea></label>
      <button class="btn" type="submit">Send invalid submission</button>
    </form>
  </section>
</div>

<section class="card">
  <h2>Reported errors</h2>
  <?php if ($reports === []): ?>
    <p class="muted">No error reports yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Endpoint</th><th>Code</th><th>Message</th><th>Status</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($reports as $report): ?>
        <tr>
          <td><?= e($report['endpoint']) ?></td>
          <td><code><?= e($report['error_code']) ?></code></td>
          <td><?= e($report['error_message']) ?></td>
          <td><?= status_label($report['status']) ?></td>
          <td><?= date_fmt($report['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>