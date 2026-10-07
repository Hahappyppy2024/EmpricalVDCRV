<div class="card">
  <h2>Members (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/members/new">Add member</a>
  <table>
    <thead><tr><th>membership_id</th><th>user</th><th>role</th><th>actions</th></tr></thead>
    <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= h($m['membership_id']) ?></td>
          <td><?= h($m['username']) ?> (<?= h($m['display_name']) ?>)</td>
          <td>
            <form method="post" action="/courses/<?= h($course_id) ?>/members/<?= h($m['membership_id']) ?>">
              <select name="role_in_course">
                <?php foreach (['admin','teacher','student','assistant','senior-assistant'] as $r): ?>
                  <option value="<?= h($r) ?>" <?= $m['role_in_course']===$r?'selected':'' ?>><?= h($r) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Update</button>
            </form>
          </td>
          <td>
            <form method="post" action="/courses/<?= h($course_id) ?>/members/<?= h($m['membership_id']) ?>/delete">
              <button type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
