<div class="card">
  <h2>New Assignment</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/assignments">
    <label>Title</label>
    <input name="title" />
    <label>Description</label>
    <textarea name="description" rows="5"></textarea>
    <label>Due at (text)</label>
    <input name="due_at" placeholder="2026-02-28T23:59:00Z" />
    <button type="submit">Create</button>
  </form>
</div>
