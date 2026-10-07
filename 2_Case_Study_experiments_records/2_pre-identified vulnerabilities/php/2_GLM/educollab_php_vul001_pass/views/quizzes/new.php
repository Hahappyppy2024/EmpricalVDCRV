<div class="card">
  <h2>New Quiz</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/quizzes">
    <label>Title</label>
    <input name="title" />
    <label>Description</label>
    <textarea name="description" rows="4"></textarea>
    <label>Open at (text)</label>
    <input name="open_at" placeholder="2026-02-28T00:00:00Z" />
    <label>Due at (text)</label>
    <input name="due_at" placeholder="2026-03-01T00:00:00Z" />
    <button type="submit">Create</button>
  </form>
</div>
