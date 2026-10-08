<?php
/**
 * Support tickets (HOST-10). Sections: list | form | detail.
 * Variables: $section, $tickets, $ticket, $isSupport
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/tickets/new">Open ticket</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>#</th><th>Subject</th><?= $isSupport ? '<th>Customer</th>' : '' ?><th>Status</th><th>Messages</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr>
                <td><?= (int) $t['id'] ?></td>
                <td><a href="/tickets/<?= (int) $t['id'] ?>"><?= e($t['subject']) ?></a></td>
                <?php if ($isSupport): ?><td><?= e($t['username']) ?></td><?php endif; ?>
                <td><span class="badge <?= badge($t['status']) ?>"><?= e($t['status']) ?></span></td>
                <td><?= (int) $t['message_count'] ?></td>
                <td><?= e($t['created_at']) ?></td>
                <td><a class="btn btn-small" href="/tickets/<?= (int) $t['id'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <form method="post" action="/tickets" class="form form-narrow">
        <label>Subject
            <input type="text" name="subject" placeholder="Describe the issue briefly" required>
        </label>
        <label>Message
            <textarea name="body" rows="6" required></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Open ticket</button>
    </form>
<?php elseif ($section === 'detail'): ?>
    <div class="toolbar">
        <a class="btn btn-small" href="/tickets">Back</a>
        <span class="badge <?= badge($ticket['status']) ?>"><?= e($ticket['status']) ?></span>
    </div>
    <p class="muted">Opened by <?= e($ticket['username']) ?> at <?= e($ticket['created_at']) ?></p>

    <?php foreach ($ticket['messages'] as $m): ?>
        <div class="message <?= $m['author_role'] === 'support' ? 'message-support' : 'message-customer' ?>">
            <div class="message-meta">
                <strong><?= e($m['username']) ?></strong> · <span class="muted"><?= e($m['author_role']) ?> · <?= e($m['created_at']) ?></span>
            </div>
            <div class="message-body"><?= nl2br(e($m['body'])) ?></div>
        </div>
    <?php endforeach; ?>

    <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/reply" class="form form-narrow">
        <label>Reply
            <textarea name="body" rows="4" required></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Post reply</button>
    </form>

    <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/status" class="form form-narrow">
        <label>Status
            <select name="status">
                <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="answered" <?= $ticket['status'] === 'answered' ? 'selected' : '' ?>>Answered</option>
                <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </label>
        <button class="btn btn-small" type="submit">Update status</button>
    </form>
<?php endif; ?>
