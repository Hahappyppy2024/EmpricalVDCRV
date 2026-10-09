<section class="card error">
    <h2>Error <?= (int)$status ?></h2>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="/">Return home</a></p>
</section>