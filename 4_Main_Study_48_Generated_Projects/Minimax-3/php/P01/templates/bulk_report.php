<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Bulk course report</h1>
    <form method="post" action="/admin/reports/bulk" class="card filters">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Category <input name="category" placeholder="e.g. Computer Science"></label>
        <label>Semester <input name="semester" placeholder="e.g. 2026-Fall"></label>
        <label>Visibility
            <select name="visibility">
                <option value="">All</option>
                <option value="open">open</option>
                <option value="closed">closed</option>
                <option value="hidden">hidden</option>
            </select>
        </label>
        <label>Format
            <select name="format">
                <option value="csv">CSV</option>
                <option value="pdf">PDF (printable HTML)</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Generate report</button>
    </form>
    <h2>Recent reports</h2>
    <?php if (empty($recent)): ?>
        <p class="empty">No reports generated yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Created</th><th>Scope</th><th>Summary</th><th>File</th><th>Requester</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= $view->e($view->formatDate($r['created_at'])) ?></td>
                    <td><?= $view->e($r['scope']) ?></td>
                    <td><?= $view->e($r['summary']) ?></td>
                    <td><?= $view->e(basename($r['file_path'])) ?></td>
                    <td><?= $view->e($r['requester_name']) ?></td>
                    <td><a class="btn" href="/reports/<?= (int)$r['id'] ?>/download">Download</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
