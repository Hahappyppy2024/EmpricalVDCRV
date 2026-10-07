<div class="card">
  <h2>Users</h2>
  <table>
    <thead><tr><th>user_id</th><th>username</th><th>display_name</th><th>created_at</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['user_id']) ?></td>
          <td><?= h($u['username']) ?></td>
          <td><?= h($u['display_name']) ?></td>
          <td><?= h($u['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
