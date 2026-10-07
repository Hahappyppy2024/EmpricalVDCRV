<div class="card">
  <h2>Edit Course</h2>
  <form method="post" action="/courses/<?= h($course['course_id']) ?>">
    <label>Title</label>
    <input name="title" value="<?= h($course['title']) ?>" />
    <label>Description</label>
    <textarea name="description" rows="4"><?= h($course['description']) ?></textarea>
    <button type="submit">Save</button>
  </form>
</div>
