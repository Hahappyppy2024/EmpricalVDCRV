<div class="card">
  <h2>New Post</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/posts">
    <label>Title</label>
    <input name="title" />
    <label>Body</label>
    <textarea name="body" rows="6"></textarea>
    <button type="submit">Create</button>
  </form>
</div>
