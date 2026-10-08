<?php
/**
 * SSL/certificate management (HOST-07). Sections: list | form | detail.
 * Variables: $section, $certificates, $certificate, $domains
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/certificates/new">Request certificate</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>Domain</th><th>Provider</th><th>Status</th><th>Not after</th><th>Renewed</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($certificates as $c): ?>
            <tr>
                <td><a href="/certificates/<?= (int) $c['id'] ?>"><?= e($c['domain_name']) ?></a></td>
                <td><?= e($c['provider']) ?></td>
                <td><span class="badge <?= badge($c['status']) ?>"><?= e($c['status']) ?></span></td>
                <td><?= e($c['not_after'] ?? '—') ?></td>
                <td><?= e($c['renewed_at'] ?? '—') ?></td>
                <td><a class="btn btn-small" href="/certificates/<?= (int) $c['id'] ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <h2>Request certificate</h2>
    <form method="post" action="/certificates" class="form form-narrow">
        <label>Domain
            <select name="domain_id" required>
                <option value="">— select —</option>
                <?php foreach ($domains as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Provider
            <select name="provider">
                <option value="letsencrypt">Let's Encrypt</option>
                <option value="zerossl">ZeroSSL</option>
                <option value="selfsigned">Self-signed</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Request</button>
    </form>

    <h2>Upload existing certificate</h2>
    <form method="post" action="/certificates/upload" class="form form-narrow">
        <label>Domain
            <select name="domain_id" required>
                <option value="">— select —</option>
                <?php foreach ($domains as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Certificate text
            <textarea name="certificate_text" rows="4" required></textarea>
        </label>
        <label>Private key
            <textarea name="private_key_text" rows="4" required></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Upload</button>
    </form>
<?php elseif ($section === 'detail'): ?>
    <div class="toolbar">
        <a class="btn btn-small" href="/certificates">Back</a>
        <form method="post" action="/certificates/<?= (int) $certificate['id'] ?>/renew" class="inline">
            <button class="btn btn-small" type="submit">Renew</button>
        </form>
    </div>
    <table class="table table-narrow">
        <tr><th>Domain</th><td><?= e($certificate['domain_name']) ?></td></tr>
        <tr><th>Provider</th><td><?= e($certificate['provider']) ?></td></tr>
        <tr><th>Status</th><td><span class="badge <?= badge($certificate['status']) ?>"><?= e($certificate['status']) ?></span></td></tr>
        <tr><th>Not before</th><td><?= e($certificate['not_before'] ?? '—') ?></td></tr>
        <tr><th>Not after</th><td><?= e($certificate['not_after'] ?? '—') ?></td></tr>
        <tr><th>Renewed</th><td><?= e($certificate['renewed_at'] ?? '—') ?></td></tr>
    </table>
    <details>
        <summary>Certificate body</summary>
        <pre><?= e($certificate['certificate_text']) ?></pre>
    </details>
<?php endif; ?>
