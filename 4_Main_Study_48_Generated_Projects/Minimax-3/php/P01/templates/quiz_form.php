<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>New quiz</h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/quizzes/new" class="card" id="quiz-form">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Title <input name="title" value="<?= $view->e($input['title']) ?>" required></label>
        <?php if (!empty($errors['title'])): ?><div class="form-error"><?= $view->e($errors['title']) ?></div><?php endif; ?>
        <label>Description <textarea name="description" rows="3"><?= $view->e($input['description']) ?></textarea></label>
        <label>Closes at <input type="datetime-local" name="closes_at" value="<?= $view->e($input['closes_at']) ?>" required></label>
        <div id="questions">
            <?php $initialQuestions = !empty($input['questions']) ? $input['questions'] : [['prompt'=>'','kind'=>'single','options'=>['',''],'correct'=>[],'points'=>1]]; ?>
            <?php foreach ($initialQuestions as $i => $q): ?>
                <fieldset class="question" data-index="<?= $i ?>">
                    <legend>Question <?= $i + 1 ?></legend>
                    <label>Prompt <input name="questions[<?= $i ?>][prompt]" value="<?= $view->e((string)($q['prompt'] ?? '')) ?>" required></label>
                    <label>Kind
                        <select name="questions[<?= $i ?>][kind]">
                            <?php foreach (['single','multi','short'] as $kind): ?>
                                <option value="<?= $kind ?>" <?= ($q['kind'] ?? '') === $kind ? 'selected' : '' ?>><?= $kind ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Points <input type="number" step="0.1" name="questions[<?= $i ?>][points]" value="<?= $view->e((string)($q['points'] ?? 1)) ?>"></label>
                    <div class="options" data-kind="<?= $view->e((string)($q['kind'] ?? 'single')) ?>">
                        <?php $opts = (array)($q['options'] ?? []); ?>
                        <?php if (in_array(($q['kind'] ?? 'single'), ['single','multi'], true)): ?>
                            <?php foreach ($opts as $j => $opt): ?>
                                <div class="option-row">
                                    <input name="questions[<?= $i ?>][options][]" value="<?= $view->e((string)$opt) ?>">
                                    <button type="button" class="remove-option">×</button>
                                </div>
                            <?php endforeach; ?>
                            <button type="button" class="add-option btn btn-ghost">Add option</button>
                        <?php endif; ?>
                        <?php if (($q['kind'] ?? 'single') === 'short'): ?>
                            <label>Expected answer
                                <input name="questions[<?= $i ?>][correct][]" value="<?= $view->e((string)($q['correct'][0] ?? '')) ?>">
                            </label>
                        <?php else: ?>
                            <div class="correct-list">
                                <?php foreach ($opts as $j => $opt): ?>
                                    <label class="correct-row">
                                        <input type="<?= ($q['kind'] ?? '') === 'multi' ? 'checkbox' : 'radio' ?>" name="questions[<?= $i ?>][correct][<?= $j ?>]" value="<?= $view->e((string)$opt) ?>" <?= in_array($opt, (array)($q['correct'] ?? []), true) ? 'checked' : '' ?>>
                                        correct: <?= $view->e((string)$opt) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($errors["q$i"])): ?><div class="form-error"><?= $view->e($errors["q$i"]) ?></div><?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($errors['questions'])): ?><div class="form-error"><?= $view->e($errors['questions']) ?></div><?php endif; ?>
        <button type="button" id="add-question" class="btn">Add question</button>
        <button class="btn btn-primary" type="submit">Create quiz</button>
    </form>
</section>
