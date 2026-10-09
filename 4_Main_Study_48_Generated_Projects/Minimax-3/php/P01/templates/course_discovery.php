<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Discover courses</h1>
        <p class="muted">Filter by title, category, instructor, or semester.</p>
    </header>

    <form class="filters card" method="get" action="/courses">
        <label>Search<input name="q" value="<?= $view->e($filters['q']) ?>"></label>
        <label>Category
            <select name="category">
                <option value="">All</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $view->e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>><?= $view->e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Semester
            <select name="semester">
                <option value="">All</option>
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?= $view->e($sem) ?>" <?= $filters['semester'] === $sem ? 'selected' : '' ?>><?= $view->e($sem) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Instructor
            <select name="instructor">
                <option value="">All</option>
                <?php foreach ($instructors as $ins): ?>
                    <option value="<?= $view->e($ins) ?>" <?= $filters['instructor'] === $ins ? 'selected' : '' ?>><?= $view->e($ins) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="/courses">Reset</a>
    </form>

    <div class="course-grid">
        <?php if (empty($results)): ?>
            <p class="empty">No courses match your filters.</p>
        <?php else: ?>
            <?php foreach ($results as $course): ?>
                <article class="card course">
                    <header><span class="badge"><?= $view->e($course['category']) ?></span><span class="badge"><?= $view->e($course['semester']) ?></span></header>
                    <h2><a href="/courses/<?= (int)$course['id'] ?>"><?= $view->e($course['code']) ?> — <?= $view->e($course['title']) ?></a></h2>
                    <p class="muted">by <?= $view->e($course['instructor_name']) ?></p>
                    <p><?= $view->e(mb_substr($course['description'], 0, 140)) ?></p>
                    <p class="badge <?= $view->e($course['visibility']) ?>"><?= $view->e($course['visibility']) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
