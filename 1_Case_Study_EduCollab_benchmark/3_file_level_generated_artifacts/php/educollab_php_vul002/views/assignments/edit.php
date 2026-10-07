<div class="card">
  <h2>Edit Assignment</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>">
    <label>Title</label>
    <input name="title" value="<?= h($assignment['title']) ?>" />
    <label>Description</label>
    <textarea name="description" rows="5"><?= h($assignment['description']) ?></textarea>
    <label>Due at (text)</label>
    <input name="due_at" value="<?= h($assignment['due_at'] ?? '') ?>" />
    <button type="submit">Save</button>
  </form>
</div>
