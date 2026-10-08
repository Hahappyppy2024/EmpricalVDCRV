<?php
namespace App\Views;
?>
<h1>Account access and recovery</h1>

<div class="grid grid--2">
  <section class="card">
    <h2>Request a password reset</h2>
    <p class="muted">A deterministic reset token is written to <code>storage/mail.log</code> and echoed in local mode.</p>
    <form data-api-form data-json="true" method="post" action="/auth/reset" class="stack" data-success-message="Reset token issued">
      <label>Email
        <input type="email" name="email" required>
      </label>
      <button class="btn btn--primary" type="submit">Request reset</button>
    </form>
  </section>

  <section class="card">
    <h2>Confirm reset</h2>
    <form data-api-form data-json="true" method="post" action="/auth/reset-confirm" data-redirect="/login" class="stack">
      <label>Email
        <input type="email" name="email" required>
      </label>
      <label>Reset token
        <input type="text" name="token" required>
      </label>
      <label>New password
        <input type="password" name="password" required>
      </label>
      <button class="btn" type="submit">Reset password</button>
    </form>
  </section>
</div>

<section class="card">
  <h2>Access and recovery history</h2>
  <?php if (($records ?? []) === []): ?>
    <p class="muted">No account access records yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>User</th><th>Kind</th><th>Status</th><th>Payload</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($records as $record): ?>
        <tr>
          <td><?= (int) $record['id'] ?></td>
          <td>#<?= (int) $record['user_id'] ?></td>
          <td><?= e($record['kind']) ?></td>
          <td><?= status_label($record['status']) ?></td>
          <td><?= e($record['payload']) ?></td>
          <td><?= date_fmt($record['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Reported client errors</h2>
  <?php if (($errors ?? []) === []): ?>
    <p class="muted">No error reports recorded.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Endpoint</th><th>Code</th><th>Message</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($errors as $report): ?>
        <tr>
          <td><?= e($report['endpoint']) ?></td>
          <td><?= e($report['error_code']) ?></td>
          <td><?= e($report['error_message']) ?></td>
          <td><?= status_label($report['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>