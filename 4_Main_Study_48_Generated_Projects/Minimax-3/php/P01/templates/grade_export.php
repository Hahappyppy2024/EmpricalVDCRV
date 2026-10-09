<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Export grades — <?= $view->e($course['code']) ?> <?= $view->e($course['title']) ?></h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/export" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Format
            <select name="format">
                <option value="csv">CSV</option>
                <option value="pdf">PDF (printable HTML)</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Generate export</button>
    </form>
</section>
