<section class="card">
    <h2>Recover account</h2>
    <form method="post" action="/api/file/account_access" class="api-form" data-method="post" data-action="recover">
        <input type="hidden" name="action" value="recover">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>Email <input type="email" name="email" required></label>
        <button type="submit">Send recovery code</button>
    </form>
    <p>The system will return the recovery code inline because no external mail service is used.</p>
</section>