<h1>Dashboard</h1>
<p>Welcome, <strong><?= htmlspecialchars($user['display_name']) ?></strong>.</p>
<div class="card-grid">
  <?php if (in_array('author', $user['roles'], true)): ?>
    <div class="card"><h3>Submit</h3><p>Submit a new paper.</p><a class="btn" href="/paper_submission">Open</a></div>
    <div class="card"><h3>Rebuttal</h3><p>Respond to reviewers.</p><a class="btn" href="/rebuttal">Open</a></div>
  <?php endif; ?>
  <?php if (in_array('reviewer', $user['roles'], true)): ?>
    <div class="card"><h3>Reviewing</h3><p>Submit reviews.</p><a class="btn" href="/reviewing">Open</a></div>
  <?php endif; ?>
  <?php if (in_array('chair', $user['roles'], true) || in_array('admin', $user['roles'], true)): ?>
    <div class="card"><h3>Phases</h3><p>Configure submission, review, rebuttal, decision phases.</p><a class="btn" href="/conference_phases">Open</a></div>
    <div class="card"><h3>Assignments</h3><p>Assign reviewers and manage conflicts.</p><a class="btn" href="/reviewer_assignment">Open</a></div>
    <div class="card"><h3>Decisions</h3><p>Record accept/reject decisions.</p><a class="btn" href="/decision_management">Open</a></div>
    <div class="card"><h3>Exports</h3><p>Run bulk CSV exports.</p><a class="btn" href="/bulk_exports">Open</a></div>
  <?php endif; ?>
  <div class="card"><h3>Discover</h3><p>Search submissions.</p><a class="btn" href="/submission_discovery">Open</a></div>
  <div class="card"><h3>API errors</h3><p>Frontend error console.</p><a class="btn" href="/frontend_api_integration_and_errors">Open</a></div>
</div>