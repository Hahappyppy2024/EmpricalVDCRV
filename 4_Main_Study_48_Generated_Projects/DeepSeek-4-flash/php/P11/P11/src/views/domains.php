<?php
/**
 * Domain management (HOST-02). Sections: list | form | detail.
 * Variables: $section, $domains, $domain, $isAdmin
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/domains/new">Add domain</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Kind</th><th>Status</th><th>DNS records</th><th><?= $isAdmin ? 'Owner' : 'Added' ?></th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($domains as $d): ?>
            <tr>
                <td><a href="/domains/<?= (int) $d['id'] ?>"><?= e($d['name']) ?></a></td>
                <td><?= e($d['kind']) ?></td>
                <td><span class="badge <?= badge($d['status']) ?>"><?= e($d['status']) ?></span></td>
                <td><?= (int) ($d['dns_count'] ?? 0) ?></td>
                <td><?= $isAdmin ? e($d['username'] ?? '') : e($d['created_at']) ?></td>
                <td>
                    <a class="btn btn-small" href="/domains/<?= (int) $d['id'] ?>">Manage</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <form method="post" action="/domains" class="form form-narrow">
        <label>Domain name
            <input type="text" name="name" placeholder="example.com" required>
        </label>
        <label>Kind
            <select name="kind">
                <option value="domain">Domain</option>
                <option value="subdomain">Subdomain</option>
                <option value="alias">Alias</option>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Add domain</button>
    </form>
<?php elseif ($section === 'detail'): ?>
    <div class="toolbar">
        <a class="btn btn-small" href="/domains">Back</a>
    </div>

    <form method="post" action="/domains/<?= (int) $domain['id'] ?>/update" class="form form-narrow">
        <label>Domain name
            <input type="text" name="name" value="<?= e($domain['name']) ?>" required>
        </label>
        <label>Kind
            <select name="kind">
                <option value="domain" <?= $domain['kind'] === 'domain' ? 'selected' : '' ?>>Domain</option>
                <option value="subdomain" <?= $domain['kind'] === 'subdomain' ? 'selected' : '' ?>>Subdomain</option>
                <option value="alias" <?= $domain['kind'] === 'alias' ? 'selected' : '' ?>>Alias</option>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="active" <?= $domain['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $domain['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>

    <form method="post" action="/domains/<?= (int) $domain['id'] ?>/status" class="inline">
        <input type="hidden" name="status" value="<?= $domain['status'] === 'active' ? 'suspended' : 'active' ?>">
        <button class="btn btn-small" type="submit"><?= $domain['status'] === 'active' ? 'Suspend' : 'Activate' ?></button>
    </form>
    <form method="post" action="/domains/<?= (int) $domain['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this domain and all DNS records?');">
        <button class="btn btn-small btn-danger" type="submit">Delete</button>
    </form>

    <h2>DNS records</h2>
    <table class="table">
        <thead>
        <tr><th>Type</th><th>Name</th><th>Value</th><th>TTL</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($domain['dns_records'] as $r): ?>
            <tr>
                <td><?= e($r['type']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['value']) ?></td>
                <td><?= (int) $r['ttl'] ?></td>
                <td>
                    <form method="post" action="/domains/<?= (int) $domain['id'] ?>/dns/<?= (int) $r['id'] ?>/delete" class="inline">
                        <button class="btn btn-small btn-danger" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" action="/domains/<?= (int) $domain['id'] ?>/dns" class="form form-narrow">
        <h3>Add DNS record</h3>
        <label>Type
            <select name="type">
                <option>A</option><option>AAAA</option><option>CNAME</option><option>MX</option><option>TXT</option>
            </select>
        </label>
        <label>Name
            <input type="text" name="name" placeholder="@ or www" required>
        </label>
        <label>Value
            <input type="text" name="value" placeholder="192.0.2.10" required>
        </label>
        <label>TTL
            <input type="number" name="ttl" value="3600" min="60">
        </label>
        <button class="btn btn-primary" type="submit">Add record</button>
    </form>
<?php endif; ?>
