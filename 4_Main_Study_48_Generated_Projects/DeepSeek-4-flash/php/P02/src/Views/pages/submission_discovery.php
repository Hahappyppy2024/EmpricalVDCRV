<?php
namespace App\Views;
?>
<?php $matches = $result['matches'] ?? []; $recent = $result['recent_searches'] ?? []; ?>
<h1>Submission discovery</h1>
<section class="card">
  <h2>Search submissions</h2>
  <form data-search-form data-endpoint="/api/conf/submission_discovery" data-target="#discovery-results" class="row">
    <label class="grow">Query
      <input type="text" name="q" placeholder="title, abstract or keywords">
    </label>
    <label>Status
      <select name="status">
        <option value="">All statuses</option>
        <option value="submitted">Submitted</option>
        <option value="under_review">Under review</option>
        <option value="in_rebuttal">In rebuttal</option>
        <option value="accepted">Accepted</option>
        <option value="rejected">Rejected</option>
        <option value="decided">Decided</option>
      </select>
    </label>
    <button class="btn btn--primary" type="submit">Search</button>
  </form>
</section>

<div id="discovery-results">
  <section class="card">
    <h2>Matching submissions</h2>
    <?php if ($matches === []): ?>
      <p class="muted">No matching submissions. Adjust your filters.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Author</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($matches as $m): ?>
          <tr>
            <td><?= (int) $m['id'] ?></td>
            <td><a href="/papers/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a></td>
            <td><?= status_label($m['status']) ?></td>
            <td><?= e($m['author_name'] ?? '') ?></td>
            <td><?= date_fmt($m['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Recent searches</h2>
  <?php if ($recent === []): ?>
    <p class="muted">No recorded searches yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Query</th><th>Status filter</th><th>Results</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['search_query']) ?: 'â€”' ?></td>
          <td><?= e($r['status_filter']) ?: 'â€”' ?></td>
          <td><?= (int) $r['result_count'] ?></td>
          <td><?= date_fmt($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>