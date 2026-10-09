<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1><?= $view->e($quiz['title']) ?></h1>
    <p class="muted"><?= $view->e($quiz['description']) ?></p>
    <?php if (!empty($attempt) && $attempt['status'] !== 'in_progress'): ?>
        <p class="banner banner-success">Score: <?= $view->e((string)$attempt['score']) ?> / <?= $view->e((string)$attempt['max_score']) ?>.</p>
    <?php endif; ?>
    <form method="post" action="/quizzes/<?= (int)$quiz['id'] ?>/take" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <?php foreach ($quiz['questions'] as $i => $q): ?>
            <fieldset>
                <legend><?= $i + 1 ?>. <?= $view->e($q['prompt']) ?></legend>
                <?php $options = json_decode($q['options_json'], true) ?: []; ?>
                <?php if ($q['kind'] === 'short'): ?>
                    <input name="answers[<?= (int)$q['id'] ?>]" value="">
                <?php else: ?>
                    <?php foreach ($options as $j => $opt): ?>
                        <label>
                            <input type="<?= $q['kind'] === 'multi' ? 'checkbox' : 'radio' ?>"
                                   name="answers[<?= (int)$q['id'] ?>]<?= $q['kind'] === 'multi' ? '[]' : '' ?>"
                                   value="<?= $view->e((string)$opt) ?>">
                            <?= $view->e((string)$opt) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>
        <div class="actions">
            <button class="btn" type="submit" name="action" value="save">Save progress</button>
            <button class="btn btn-primary" type="submit" name="action" value="submit">Submit quiz</button>
        </div>
    </form>
</section>
