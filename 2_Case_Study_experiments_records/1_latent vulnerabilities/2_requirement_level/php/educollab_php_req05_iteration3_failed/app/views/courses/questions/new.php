<h1>Create Question</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions">
    <label>Question Type</label>
    <input type="text" name="qtype" placeholder="mcq, short-answer, true-false" required>
    <label>Prompt</label>
    <textarea name="prompt" rows="6" required></textarea>
    <label>Options JSON</label>
    <textarea name="options_json" rows="4" placeholder='["A","B","C"]'></textarea>
    <label>Answer JSON</label>
    <textarea name="answer_json" rows="4" placeholder='{"correct":"A"}'></textarea>
    <button type="submit">Create question</button>
</form>
