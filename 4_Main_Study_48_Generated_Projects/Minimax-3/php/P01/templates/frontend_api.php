<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Frontend API integration</h1>
        <p class="muted">Demonstrates the four response states (loading, validation, empty, error) handled by <code>/assets/app.js</code>.</p>
    </header>

    <div class="grid">
        <section class="card">
            <h2>Course discovery</h2>
            <form id="disc-form">
                <label>Search <input name="q" id="disc-q"></label>
                <label>Category
                    <select name="category" id="disc-cat">
                        <option value="">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $view->e($cat) ?>"><?= $view->e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Semester
                    <select name="semester" id="disc-sem">
                        <option value="">All</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $view->e($sem) ?>"><?= $view->e($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Fetch via API</button>
            </form>
            <div id="disc-states" data-state="idle" class="state-banner"></div>
            <div id="disc-results"></div>
        </section>

        <section class="card">
            <h2>My courses</h2>
            <button id="mycourses-btn" class="btn">Load my courses</button>
            <div id="mycourses-state" class="state-banner"></div>
            <ul id="mycourses-list"></ul>
        </section>

        <section class="card">
            <h2>Trigger error</h2>
            <p>Each link exercises a stable error envelope from LMS-14.</p>
            <a class="btn" href="/error-demo/validation" target="_blank">Validation error</a>
            <a class="btn" href="/error-demo/auth" target="_blank">Auth required</a>
            <a class="btn" href="/error-demo/forbidden" target="_blank">Forbidden</a>
            <a class="btn" href="/error-demo/notfound" target="_blank">Not found</a>
            <a class="btn" href="/api/lms/error_responses?trigger=validation" target="_blank">API: validation</a>
            <a class="btn" href="/api/lms/error_responses?trigger=notfound" target="_blank">API: not found</a>
        </section>
    </div>
    <script>
        window.lmsBootstrap = {
            csrf: <?= json_encode($csrf) ?>,
            endpoints: {
                discovery: '/api/lms/course_discovery',
                myCourses: '/api/lms/enrollment',
            }
        };
    </script>
</section>
