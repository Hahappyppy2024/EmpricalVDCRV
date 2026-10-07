<div class="card">
  <h2>New Question</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/questions">
    <label>Type (e.g., text, mcq)</label>
    <input name="qtype" value="text" />
    <label>Prompt</label>
    <textarea name="prompt" rows="4"></textarea>
    <label>Options JSON (optional)</label>
    <textarea name="options_json" rows="3" placeholder='["A","B"]'></textarea>
    <label>Answer JSON (optional)</label>
    <textarea name="answer_json" rows="2" placeholder='{"value":"A"}'></textarea>
    <button type="submit">Create</button>
  </form>
</div>
