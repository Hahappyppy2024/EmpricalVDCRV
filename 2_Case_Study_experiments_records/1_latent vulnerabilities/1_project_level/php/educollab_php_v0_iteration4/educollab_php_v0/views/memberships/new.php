<div class="card">
    <h2>Add Member (course <?= h($course_id) ?>)</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/members">
        <?= csrf_input() ?>
        <label>User ID</label>
        <input name="user_id" type="number" />
        <label>Role</label>
        <select name="role_in_course">
            <option value="student">student</option>
            <option value="ta">ta</option>
            <option value="teacher">teacher</option>
        </select>
        <div style="margin-top:12px"><button type="submit">Add</button></div>
    </form>
</div>