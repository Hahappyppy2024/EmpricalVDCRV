<h1><?= htmlspecialchars($appName) ?></h1>
<p class="lead">A synthetic benchmark mirroring a conference peer review workflow: account access, phases, submission, reviewing, rebuttal, decisions, and exports.</p>
<div class="card-grid">
  <div class="card">
    <h3>For Authors</h3>
    <p>Submit papers, view status, respond to reviewers, see decisions.</p>
    <a class="btn" href="/login">Sign in</a>
  </div>
  <div class="card">
    <h3>For Reviewers</h3>
    <p>Accept assignments, submit reviews, read allowed rebuttals.</p>
    <a class="btn" href="/login">Sign in</a>
  </div>
  <div class="card">
    <h3>For Chairs</h3>
    <p>Manage phases, assign reviewers, record decisions, run exports.</p>
    <a class="btn" href="/login">Sign in</a>
  </div>
</div>
<h2>Demo Accounts</h2>
<table class="data-table">
  <thead><tr><th>Username</th><th>Role</th><th>Password</th></tr></thead>
  <tbody>
    <tr><td>admin</td><td>admin</td><td>Password123!</td></tr>
    <tr><td>chair1</td><td>chair, reviewer</td><td>Password123!</td></tr>
    <tr><td>reviewer1</td><td>reviewer</td><td>Password123!</td></tr>
    <tr><td>author1</td><td>author</td><td>Password123!</td></tr>
  </tbody>
</table>