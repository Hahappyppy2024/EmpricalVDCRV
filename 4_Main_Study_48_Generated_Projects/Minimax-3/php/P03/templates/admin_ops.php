<?php $page_title = $page_title ?? 'Admin operations'; ?>
<section>
  <h1>Admin operations</h1>

  <div class="grid two">
    <div>
      <h2>Promotions</h2>
      <form method="post" action="/admin/operations/promotions" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Code <input type="text" name="code" required></label>
        <label>Description <input type="text" name="description"></label>
        <label>Percent off <input type="number" name="percent_off" min="0" max="100" required></label>
        <button class="btn btn-primary" type="submit">Create</button>
      </form>
      <table class="table">
        <thead><tr><th>Code</th><th>Description</th><th>Percent</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($promotions as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['code'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$p['percent_off'] ?>%</td>
              <td><?= ((int)$p['active']) === 1 ? 'Yes' : 'No' ?></td>
              <td>
                <form method="post" action="/admin/operations/promotions/<?= (int)$p['id'] ?>/toggle" class="inline">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <button class="btn btn-ghost" type="submit">Toggle</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div>
      <h2>Users</h2>
      <table class="table">
        <thead><tr><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($u['status'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <form method="post" action="/admin/operations/users/<?= (int)$u['id'] ?>/status" class="inline">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <select name="status">
                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>active</option>
                    <option value="disabled" <?= $u['status'] === 'disabled' ? 'selected' : '' ?>>disabled</option>
                  </select>
                  <button class="btn btn-ghost" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <h2>Settings</h2>
  <form method="post" action="/admin/operations/settings" class="inline-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Key <input type="text" name="key" required></label>
    <label>Value <input type="text" name="value" required></label>
    <button class="btn btn-primary" type="submit">Save</button>
  </form>
  <ul>
    <?php foreach ($settings as $s): ?>
      <li><code><?= htmlspecialchars($s['key'], ENT_QUOTES, 'UTF-8') ?></code> = <?= htmlspecialchars($s['value'], ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>

  <h2>Audit trail</h2>
  <ul class="audit">
    <?php foreach ($audits as $a): ?>
      <li><?= htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($a['actor_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($a['action'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($a['entity_type'], ENT_QUOTES, 'UTF-8') ?>#<?= htmlspecialchars($a['entity_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</section>