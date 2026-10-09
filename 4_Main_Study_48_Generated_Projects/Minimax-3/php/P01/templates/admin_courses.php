<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Courses</h1>
        <a class="btn btn-primary" href="/admin/courses/new">Create course</a>
    </header>
    <table class="table">
        <thead><tr><th>Code</th><th>Title</th><th>Category</th><th>Semester</th><th>Visibility</th><th>Instructor</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td><?= $view->e($c['code']) ?></td>
                <td><a href="/courses/<?= (int)$c['id'] ?>"><?= $view->e($c['title']) ?></a></td>
                <td><?= $view->e($c['category']) ?></td>
                <td><?= $view->e($c['semester']) ?></td>
                <td>
                    <form method="post" action="/admin/courses/<?= (int)$c['id'] ?>/visibility">
                        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                        <select name="visibility">
                            <?php foreach (['open','closed','hidden'] as $v): ?>
                                <option value="<?= $v ?>" <?= ($c['visibility'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="submit">Set</button>
                    </form>
                </td>
                <td><?= $view->e($c['instructor_name']) ?></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
