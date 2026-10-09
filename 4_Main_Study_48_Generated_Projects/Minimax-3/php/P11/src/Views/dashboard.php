<h1>Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h1>
<div class="grid">
  <a class="card stat" href="/domains">
    <span class="big"><?= (int)$counts['domains'] ?></span>
    <span>Domains</span>
  </a>
  <a class="card stat" href="/sites">
    <span class="big"><?= (int)$counts['sites'] ?></span>
    <span>Sites</span>
  </a>
  <a class="card stat" href="/tickets">
    <span class="big"><?= (int)$counts['tickets'] ?></span>
    <span>Tickets</span>
  </a>
  <a class="card stat" href="/backups">
    <span class="big"><?= (int)$counts['backups'] ?></span>
    <span>Backups</span>
  </a>
</div>

<div class="card">
  <h2>Resource usage summary</h2>
  <ul class="kv">
    <li><span>Avg CPU</span><b><?= htmlspecialchars((string)$resource['avg_cpu']) ?>%</b></li>
    <li><span>Max CPU</span><b><?= htmlspecialchars((string)$resource['max_cpu']) ?>%</b></li>
    <li><span>Avg disk</span><b><?= htmlspecialchars((string)$resource['avg_disk']) ?> MB</b></li>
    <li><span>Total bandwidth</span><b><?= htmlspecialchars((string)$resource['total_bw']) ?> MB</b></li>
    <li><span>Emails sent</span><b><?= htmlspecialchars((string)$resource['total_emails']) ?></b></li>
    <li><span>Plan disk quota</span><b><?= htmlspecialchars((string)$resource['plan_disk_mb']) ?> MB</b></li>
    <li><span>Plan bandwidth quota</span><b><?= htmlspecialchars((string)$resource['plan_bw_mb']) ?> MB</b></li>
  </ul>
  <a class="btn" href="/resources">Open resource usage</a>
</div>

<div class="card">
  <h2>Quick actions</h2>
  <ul class="quick">
    <li><a href="/domains">Add a domain</a></li>
    <li><a href="/sites">Create a site</a></li>
    <li><a href="/files">Upload a file</a></li>
    <li><a href="/databases">Create a database</a></li>
    <li><a href="/tickets">Open a support ticket</a></li>
  </ul>
</div>