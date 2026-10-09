<?php /** @var array $run */ ?>
<header class="page-header">
    <h1>Run #<?= (int)$run['id'] ?></h1>
    <p class="muted">SYS-06 · job <?= htmlspecialchars($run['job_name']) ?> · profile <?= htmlspecialchars($run['profile_code']) ?></p>
</header>

<section class="card">
    <p><strong>Status:</strong> <span class="badge badge-<?= htmlspecialchars($run['status']) ?>"><?= htmlspecialchars($run['status']) ?></span></p>
    <p><strong>Exit code:</strong> <?= (int)$run['exit_code'] ?></p>
    <p><strong>Started:</strong> <?= htmlspecialchars((string)$run['started_at']) ?></p>
    <p><strong>Finished:</strong> <?= htmlspecialchars((string)$run['finished_at']) ?: '—' ?></p>
    <p><strong>Duration:</strong> <?= (int)$run['duration_ms'] ?> ms</p>
    <p><strong>Retry count:</strong> <?= (int)$run['retry_count'] ?></p>
</section>

<section class="card">
    <h3>Output</h3>
    <pre class="pre"><?= htmlspecialchars((string)$run['output']) ?></pre>
</section>

<section class="card">
    <h3>Error output</h3>
    <pre class="pre"><?= htmlspecialchars((string)$run['error_output']) ?: '(empty)' ?></pre>
</section>