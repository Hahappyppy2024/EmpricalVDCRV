<?php
/**
 * Scheduled tasks (HOST-08). Sections: list | form.
 * Variables: $section, $tasks, $task
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/cron/new">New task</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Command</th><th>Schedule</th><th>Enabled</th><th>Status</th><th>Last run</th><th>Next run</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
            <tr>
                <td><?= e($t['name']) ?></td>
                <td><code><?= e($t['command']) ?></code></td>
                <td><?= e($t['schedule']) ?></td>
                <td><?= (int) $t['enabled'] === 1 ? 'Yes' : 'No' ?></td>
                <td><span class="badge <?= badge($t['status']) ?>"><?= e($t['status']) ?></span></td>
                <td><?= e($t['last_run'] ?? '—') ?></td>
                <td><?= e($t['next_run'] ?? '—') ?></td>
                <td>
                    <form method="post" action="/cron/<?= (int) $t['id'] ?>/toggle" class="inline">
                        <button class="btn btn-small" type="submit"><?= (int) $t['enabled'] === 1 ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post" action="/cron/<?= (int) $t['id'] ?>/run" class="inline">
                        <button class="btn btn-small" type="submit">Run now</button>
                    </form>
                    <form method="post" action="/cron/<?= (int) $t['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this task?');">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
            <?php if ($t['last_output'] !== ''): ?>
                <tr class="row-sub">
                    <td colspan="8" class="muted"><strong>Last output:</strong> <?= e($t['last_output']) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <form method="post" action="/cron" class="form form-narrow">
        <label>Task name
            <input type="text" name="name" placeholder="Daily site backup" required>
        </label>
        <label>Command
            <input type="text" name="command" placeholder="backup --site main" required>
        </label>
        <label>Schedule (5-field cron)
            <input type="text" name="schedule" value="0 2 * * *" required>
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="enabled" value="1" checked> Enabled
        </label>
        <button class="btn btn-primary" type="submit">Create task</button>
    </form>
<?php endif; ?>
