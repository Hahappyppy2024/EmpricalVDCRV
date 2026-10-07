<div class="card">
    <h2>Course: <?= h($course['title']) ?></h2>
    <p><?= h($course['description']) ?></p>
    <p class="muted">course_id=<?= h($course['course_id']) ?> | created_by=<?= h($course['creator_username'] ?? '') ?></p>

    <div style="margin:12px 0">
        <a href="/courses/<?= h($course['course_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course['course_id']) ?>/delete">
            <?= csrf_input() ?>
            <button type="submit">Delete</button>
        </form>
    </div>

    <h3>Members</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/members">Manage members</a></p>

    <h3>Posts</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/posts/new">Create post</a></p>
    <?php if (empty($posts)): ?>
        <p class="muted">No posts yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="/courses/<?= h($course['course_id']) ?>/posts/<?= h($post['post_id']) ?>">
                        <?= h($post['title']) ?>
                    </a>
                    <span class="muted">by <?= h($post['author_username']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Assignments</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/assignments/new">New assignment</a></p>
    <?php if (empty($assignments)): ?>
        <p class="muted">No assignments yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($assignments as $a): ?>
                <li>
                    <a href="/courses/<?= h($course['course_id']) ?>/assignments/<?= h($a['assignment_id']) ?>">
                        <?= h($a['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Uploads</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/uploads">View uploads</a></p>

    <h3>Questions</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/questions">Question bank</a></p>

    <h3>Quizzes</h3>
    <p><a href="/courses/<?= h($course['course_id']) ?>/quizzes/new">New quiz</a></p>
    <?php if (empty($quizzes)): ?>
        <p class="muted">No quizzes yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($quizzes as $q): ?>
                <li>
                    <a href="/courses/<?= h($course['course_id']) ?>/quizzes/<?= h($q['quiz_id']) ?>">
                        <?= h($q['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>