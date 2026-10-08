<?php
namespace App\Views;
?>
<section class="card">
  <h1>Sign in</h1>
  <p class="muted">Use a seeded account to explore the system.</p>
  <form data-api-form method="post" action="/auth/login" data-redirect="/dashboard" class="stack">
    <label>Email
      <input type="email" name="email" autocomplete="email" placeholder="author@example.com" required>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required>
    </label>
    <button class="btn btn--primary" type="submit">Sign in</button>
    <p class="muted">
      <a href="/reset-password">Forgot your password?</a> Â·
      <a href="/register">Create an account</a>
    </p>
  </form>
</section>
<div class="card">
  <h2>Seed accounts</h2>
  <table class="table">
    <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
    <tbody>
      <tr><td>admin</td><td>admin@example.com</td><td>Admin@123</td></tr>
      <tr><td>chair</td><td>chair@example.com</td><td>Chair@123</td></tr>
      <tr><td>author</td><td>author@example.com</td><td>Author@123</td></tr>
      <tr><td>author</td><td>author2@example.com</td><td>Author2@123</td></tr>
      <tr><td>reviewer</td><td>reviewer@example.com</td><td>Reviewer@123</td></tr>
      <tr><td>reviewer</td><td>reviewer2@example.com</td><td>Reviewer2@123</td></tr>
    </tbody>
  </table>
</div>