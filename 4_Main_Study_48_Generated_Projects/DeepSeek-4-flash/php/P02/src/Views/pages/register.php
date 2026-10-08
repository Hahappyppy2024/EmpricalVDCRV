<?php
namespace App\Views;
?>
<section class="card">
  <h1>Create account</h1>
  <form data-api-form method="post" action="/auth/register" data-redirect="/dashboard" class="stack">
    <label>Name
      <input type="text" name="name" required>
    </label>
    <label>Email
      <input type="email" name="email" required>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <button class="btn btn--primary" type="submit">Create account</button>
    <p class="muted"><a href="/login">Already have an account? Sign in</a></p>
  </form>
</section>