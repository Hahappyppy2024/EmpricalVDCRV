<div class="card">
  <h2>Edit Post</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>">
    <label>Title</label>
    <input name="title" value="<?= h($post['title']) ?>" />
    <label>Body</label>
    <textarea name="body" rows="6"><?= h($post['body']) ?></textarea>
    <button type="submit">Save</button>
  </form>
</div>
