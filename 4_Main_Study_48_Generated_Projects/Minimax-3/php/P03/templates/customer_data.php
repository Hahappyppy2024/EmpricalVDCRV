<?php $page_title = $page_title ?? 'Customer data'; ?>
<section>
  <h1>Customer data</h1>
  <p>Signed in as <strong><?= htmlspecialchars($profile_user['display_name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($profile_user['email'], ENT_QUOTES, 'UTF-8') ?>).</p>

  <div class="grid two">
    <div>
      <h2>Preferences</h2>
      <form method="post" action="/customer/data/preferences" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Display name <input type="text" name="display_name" value="<?= htmlspecialchars($profile_user['display_name'], ENT_QUOTES, 'UTF-8') ?>"></label>
        <label class="checkbox"><input type="checkbox" name="newsletter" value="1" <?= !empty($preferences['newsletter']) ? 'checked' : '' ?>> Subscribe to newsletter</label>
        <label class="checkbox"><input type="checkbox" name="marketing_opt_in" value="1" <?= !empty($preferences['marketing_opt_in']) ? 'checked' : '' ?>> Marketing opt-in</label>
        <label>Preferred currency <input type="text" name="preferred_currency" value="<?= htmlspecialchars($preferences['preferred_currency'] ?? 'USD', ENT_QUOTES, 'UTF-8') ?>"></label>
        <label>Notes <textarea name="notes"><?= htmlspecialchars($preferences['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label>
        <button class="btn btn-primary" type="submit">Save</button>
      </form>
    </div>
    <div>
      <h2>Addresses</h2>
      <ul class="addresses">
        <?php foreach ($addresses as $a): ?>
          <li>
            <strong><?= htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') ?><?= ((int)$a['is_default']) === 1 ? ' (default)' : '' ?></strong><br>
            <?= htmlspecialchars($a['full_name'], ENT_QUOTES, 'UTF-8') ?><br>
            <?= htmlspecialchars($a['line1'], ENT_QUOTES, 'UTF-8') ?><br>
            <?php if (!empty($a['line2'])): ?><?= htmlspecialchars($a['line2'], ENT_QUOTES, 'UTF-8') ?><br><?php endif; ?>
            <?= htmlspecialchars($a['city'] . ', ' . $a['postal_code'], ENT_QUOTES, 'UTF-8') ?><br>
            <form method="post" action="/customer/data/addresses/<?= (int)$a['id'] ?>/delete" class="inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <button class="btn btn-danger" type="submit">Delete</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <h3>Add address</h3>
      <form method="post" action="/customer/data/addresses" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Label <input type="text" name="label" value="home"></label>
        <label>Full name <input type="text" name="full_name" required></label>
        <label>Line 1 <input type="text" name="line1" required></label>
        <label>Line 2 <input type="text" name="line2"></label>
        <label>City <input type="text" name="city" required></label>
        <label>Region <input type="text" name="region"></label>
        <label>Postal code <input type="text" name="postal_code" required></label>
        <label>Country <input type="text" name="country" value="US"></label>
        <label class="checkbox"><input type="checkbox" name="is_default" value="1"> Make default</label>
        <button class="btn btn-primary" type="submit">Save address</button>
      </form>
    </div>
  </div>
</section>