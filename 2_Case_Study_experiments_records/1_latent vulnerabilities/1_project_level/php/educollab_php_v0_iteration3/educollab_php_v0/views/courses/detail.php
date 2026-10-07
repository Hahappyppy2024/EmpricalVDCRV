<div class="card">
    <h2><?= h($course['title']) ?></h2>
    <p><?= nl2br(h($course['description'])) ?></p>
    <p class="muted">Created at <?= h($course['created_at']) ?> | Updated at <?= h($course['updated_at']) ?></p>

    <div>
        <a href="/courses/<?= h($course['course_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course['course_id']) ?>/delete"><?= csrf_input() ?><button type="submit">Delete</button></form>
    </div>

    <hr />

    <div style="display:flex; gap:12px; flex-wrap:wrap">
        <a href="/courses/<?= h($course['course_id']) ?>/members">Members</a>
        <a href="/courses/<?= h($course['course_id']) ?>/posts">Posts</a>
        <a href="/courses/<?= h($course['course_id']) ?>/assignments">Assignments</a>
        <a href="/courses/<?= h($course['course_id']) ?>/uploads">Uploads</a>
        <a href="/courses/<?= h($course['course_id']) ?>/questions">Question Bank</a>
        <a href="/courses/<?= h($course['course_id']) ?>/quizzes">Quizzes</a>
        <a href="/courses/<?= h($course['course_id']) ?>/search">Search Posts</a>
        <a href="/courses/<?= h($course['course_id']) ?>/search/comments">Search Comments</a>
    </div>
</div>