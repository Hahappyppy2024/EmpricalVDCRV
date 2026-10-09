<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>My enrollments</h1>
    <?php if (empty($rows)): ?>
        <p class="empty">You have not enrolled in any courses yet. <a href="/courses">Discover courses</a>.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Code</th><th>Title</th><th>Category</th><th>Semester</th><th>Instructor</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $view->e($r['code']) ?></td>
                    <td><a href="/courses/<?= (int)$r['course_id'] ?>"><?= $view->e($r['title']) ?></a></td>
                    <td><?= $view->e($r['category']) ?></td>
                    <td><?= $view->e($r['semester']) ?></td>
                    <td><?= $view->e($r['instructor_name']) ?></td>
                    <td>
                        <a class="btn btn-ghost" href="/courses/<?= (int)$r['course_id'] ?>/materials">Materials</a>
                        <a class="btn btn-ghost" href="/courses/<?= (int)$r['course_id'] ?>/announcements">Announcements</a>
                        <a class="btn btn-ghost" href="/courses/<?= (int)$r['course_id'] ?>/discussion">Discussion</a>
                        <a class="btn btn-ghost" href="/courses/<?= (int)$r['course_id'] ?>/assignments">Assignments</a>
                        <a class="btn btn-ghost" href="/courses/<?= (int)$r['course_id'] ?>/quizzes">Quizzes</a>
                        <form method="post" action="/enroll/<?= (int)$r['course_id'] ?>/drop" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?= $view->e($csrf ?? '') ?>">
                            <button class="btn btn-danger" type="submit">Drop</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
