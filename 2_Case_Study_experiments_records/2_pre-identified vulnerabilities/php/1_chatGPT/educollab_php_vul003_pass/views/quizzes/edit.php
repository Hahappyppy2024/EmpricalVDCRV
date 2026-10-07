<div class="card">
  <h2>Edit Quiz</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>">
    <label>Title</label>
    <input name="title" value="<?= h($quiz['title']) ?>" />
    <label>Description</label>
    <textarea name="description" rows="4"><?= h($quiz['description']) ?></textarea>
    <label>Open at</label>
    <input name="open_at" value="<?= h($quiz['open_at'] ?? '') ?>" />
    <label>Due at</label>
    <input name="due_at" value="<?= h($quiz['due_at'] ?? '') ?>" />
    <button type="submit">Save</button>
  </form>
</div>
