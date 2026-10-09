<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Announcements — <?= $view->e($course['code']) ?></h1>
        <?php if (!empty($canPost)): ?>
            <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/announcements/new">New announcement</a>
        <?php endif; ?>
    </header>
    <?php if (empty($rows)): ?>
        <p class="empty">No announcements have been posted yet.</p>
    <?php else: ?>
        <?php foreach ($rows as $a): ?>
            <article class="card">
                <header>
                    <h2><?= $view->e($a['title']) ?></h2>
                    <p class="muted">By <?= $view->e($a['poster_name']) ?> · <?= $view->e($view->formatDate($a['created_at'])) ?></p>
                </header>
                <p><?= nl2br($view->e($a['body'])) ?></p>
                <?php if (!empty($canPost)): ?>
                    <form method="post" action="/announcements/<?= (int)$a['id'] ?>/delete">
                        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
