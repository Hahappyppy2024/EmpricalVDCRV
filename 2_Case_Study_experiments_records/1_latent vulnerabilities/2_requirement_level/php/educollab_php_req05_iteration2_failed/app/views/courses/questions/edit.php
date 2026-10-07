<h1>Edit Question</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions/<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?>">
    <label>Question Type</label>
    <input type="text" name="qtype" value="<?= htmlspecialchars($question['qtype'], ENT_QUOTES, 'UTF-8') ?>" required>
    <label>Prompt</label>
    <textarea name="prompt" rows="6" required><?= htmlspecialchars($question['prompt'], ENT_QUOTES, 'UTF-8') ?></textarea>
    <label>Options JSON</label>
    <textarea name="options_json" rows="4"><?= htmlspecialchars((string) ($question['options_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    <label>Answer JSON</label>
    <textarea name="answer_json" rows="4"><?= htmlspecialchars((string) ($question['answer_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    <button type="submit">Save question</button>
</form>
