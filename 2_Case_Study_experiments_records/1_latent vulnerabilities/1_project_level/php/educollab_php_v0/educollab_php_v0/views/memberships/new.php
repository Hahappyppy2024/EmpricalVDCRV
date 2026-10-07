<div class="card">
  <h2>Add Member (course <?= h($course_id) ?>)</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/members">
    <label>User ID</label>
    <input name="user_id" />
    <?php if (!empty($users)): ?>
      <p class="muted">(Admin convenience) Existing users:</p>
      <ul>
        <?php foreach ($users as $u): ?>
          <li>#<?= h($u['user_id']) ?> - <?= h($u['username']) ?> (<?= h($u['display_name']) ?>)</li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <label>Role</label>
    <select name="role_in_course">
      <?php foreach (['student','assistant','senior-assistant','teacher','admin'] as $r): ?>
        <option value="<?= h($r) ?>"><?= h($r) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Add</button>
  </form>
</div>
