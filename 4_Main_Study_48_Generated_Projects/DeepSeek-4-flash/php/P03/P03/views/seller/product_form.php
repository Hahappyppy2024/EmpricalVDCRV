<?php $pageTitle = $product === null ? 'New product' : 'Edit ' . $product['name']; ?>
<h1><?= $product === null ? 'New product' : 'Edit product' ?></h1>

<form method="post" action="<?= $product === null ? '/seller/products/new' : '/seller/products/' . (int) $product['id'] . '/edit' ?>"
      enctype="multipart/form-data" class="card form" data-jsproduct>
  <?= csrf_field($csrf) ?>
  <label>Name <input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required></label>
  <label>Category
    <select name="category_id" required>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="row2">
    <label>Price <input type="number" step="0.01" min="0" name="price" value="<?= e((string) ($product['price'] ?? '0.00')) ?>" required></label>
    <label>Stock <input type="number" step="1" min="0" name="stock" value="<?= e((string) ($product['stock'] ?? '0')) ?>" required></label>
  </div>
  <label>Description <textarea name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea></label>
  <label>Image (JPG, PNG, WEBP, GIF, SVG)
    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,.svg">
  </label>
  <?php if ($product !== null && $product['image'] !== ''): ?>
    <p class="muted small">Current: <img src="/uploads/<?= e($product['image']) ?>" alt="" class="thumb"> <?= e($product['image']) ?></p>
  <?php endif; ?>
  <div class="check-row">
    <label><input type="checkbox" name="active" value="1" <?= $product === null || (int) $product['active'] === 1 ? 'checked' : '' ?>> Visible in catalog</label>
    <label><input type="checkbox" name="featured" value="1" <?= $product !== null && (int) $product['featured'] === 1 ? 'checked' : '' ?>> Featured</label>
  </div>
  <button class="btn btn-primary" type="submit"><?= $product === null ? 'Create product' : 'Save changes' ?></button>
  <a class="btn btn-ghost" href="/seller/products">Cancel</a>
</form>
