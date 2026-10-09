<section class="card">
    <h2>Upload a file</h2>
    <form method="post" action="/api/file/file_upload" enctype="multipart/form-data" class="api-form" data-method="post" data-redirect="/files">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>Folder
            <select name="folder_id">
                <option value="">(personal root)</option>
                <?php foreach ($folders as $folder): ?>
                    <option value="<?= (int)$folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Team
            <select name="team_id">
                <option value="">(none)</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Description <input type="text" name="description"></label>
        <label>Tags (comma separated) <input type="text" name="tags"></label>
        <label>File <input type="file" name="file" required></label>
        <button type="submit">Upload</button>
    </form>
</section>