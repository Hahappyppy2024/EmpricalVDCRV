<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Materials — <?= $view->e($course['code']) ?></h1>
        <?php if (!empty($canUpload)): ?>
            <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/materials/new">Upload material</a>
        <?php endif; ?>
    </header>
    <?php if (empty($rows)): ?>
        <p class="empty">No materials have been uploaded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Title</th><th>Description</th><th>Uploader</th><th>Uploaded</th><th>Download</th><?php if (!empty($canUpload)): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $m): ?>
                <tr>
                    <td><?= $view->e($m['title']) ?></td>
                    <td><?= $view->e($m['description']) ?></td>
                    <td><?= $view->e($m['uploader_name']) ?></td>
                    <td><?= $view->e($view->formatDate($m['created_at'])) ?></td>
                    <td><a class="btn" href="/materials/<?= (int)$m['id'] ?>/download">Download</a></td>
                    <?php if (!empty($canUpload)): ?>
                        <td>
                            <form method="post" action="/materials/<?= (int)$m['id'] ?>/delete" style="display:inline">
                                <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
