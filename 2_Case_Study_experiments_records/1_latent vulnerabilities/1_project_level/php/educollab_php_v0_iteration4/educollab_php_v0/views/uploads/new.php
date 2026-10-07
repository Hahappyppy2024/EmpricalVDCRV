<div class="card">
    <h2>Upload File (course <?= h($course_id) ?>)</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/uploads" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <label>Choose file</label>
        <input type="file" name="file" />
        <div style="margin-top:12px"><button type="submit">Upload</button></div>
    </form>
</div>