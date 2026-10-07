<div class="card">
    <h2>New Question</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/questions">
        <?= csrf_input() ?>
        <label>Type (e.g., text, mcq)</label>
        <input name="qtype" />
        <label>Prompt</label>
        <textarea name="prompt"></textarea>
        <label>Options JSON</label>
        <textarea name="options_json"></textarea>
        <label>Answer JSON</label>
        <textarea name="answer_json"></textarea>
        <div style="margin-top:12px"><button type="submit">Create</button></div>
    </form>
</div>