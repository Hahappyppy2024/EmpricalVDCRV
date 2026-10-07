<div class="card">
    <h2>New Course</h2>
    <form method="post" action="/courses">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" />
        <label>Description</label>
        <textarea name="description"></textarea>
        <div style="margin-top:12px"><button type="submit">Create</button></div>
    </form>
</div>