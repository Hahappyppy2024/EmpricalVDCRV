<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Discussion — <?= $view->e($course['code']) ?></h1>
        <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/discussion/new">New thread</a>
    </header>
    <form method="get" action="/courses/<?= (int)$course['id'] ?>/discussion" class="filters card">
        <label>Search <input name="q" value="<?= $view->e($search) ?>"></label>
        <button class="btn" type="submit">Search</button>
    </form>
    <?php if (empty($threads)): ?>
        <p class="empty">No threads yet. Start one!</p>
    <?php else: ?>
        <?php foreach ($threads as $t): ?>
            <article class="card thread">
                <header>
                    <strong><?= $view->e($t['thread']['author_name']) ?></strong>
                    <span class="muted"><?= $view->e($view->formatDate($t['thread']['created_at'])) ?></span>
                    <span class="badge"><?= (int)$t['thread']['reply_count'] ?> replies</span>
                </header>
                <p><?= nl2br($view->e($t['thread']['body'])) ?></p>
                <?php if (!empty($t['replies'])): ?>
                    <ul class="replies">
                    <?php foreach ($t['replies'] as $r): ?>
                        <li>
                            <strong><?= $view->e($r['author_name']) ?></strong>
                            <span class="muted"><?= $view->e($view->formatDate($r['created_at'])) ?></span>
                            <p><?= nl2br($view->e($r['body'])) ?></p>
                            <?php if ((int)$r['author_id'] === (int)$user['id'] || ($user['role'] ?? '') === 'admin'): ?>
                                <a class="btn btn-ghost" href="/discussion/<?= (int)$r['id'] ?>/edit">Edit</a>
                                <form method="post" action="/discussion/<?= (int)$r['id'] ?>/delete" style="display:inline">
                                    <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="/discussion/<?= (int)$t['thread']['id'] ?>/reply" class="reply-form">
                    <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                    <label>Reply <textarea name="body" rows="2" required></textarea></label>
                    <button class="btn" type="submit">Reply</button>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
