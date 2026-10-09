<section class="card">
    <h2>Search</h2>
    <form method="get" action="/api/file/search" class="api-form" data-method="get">
        <input type="text" name="q" placeholder="Name contains">
        <input type="text" name="owner" placeholder="Owner email">
        <input type="text" name="tag" placeholder="Tag">
        <input type="date" name="from_date">
        <input type="date" name="to_date">
        <button type="submit">Search</button>
    </form>
    <h3>Saved searches</h3>
    <ul>
    <?php foreach ($saved as $search): ?>
        <li><?= htmlspecialchars($search['name']) ?> (<?= htmlspecialchars((string)$search['query']) ?>)</li>
    <?php endforeach; ?>
    </ul>
</section>