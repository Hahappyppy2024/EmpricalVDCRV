<?php
declare(strict_types=1);

function seed(\PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $tables = [
        'sessions', 'csrf_tokens', 'account_recoveries', 'folders', 'team_spaces', 'team_memberships',
        'stored_files', 'file_tags', 'versions', 'file_uploads', 'shares', 'trash_items',
        'storage_quotas', 'searches', 'audit_events', 'audit_exports', 'admin_settings',
        'account_access', 'folder_management', 'sharing_links', 'team_spaces_log',
        'version_history', 'trash_log', 'storage_quota_log', 'audit_log_exports',
        'admin_console_log', 'file_download_log', 'users'
    ];
    foreach ($tables as $t) {
        $pdo->exec('DELETE FROM ' . $t);
    }
    $pdo->exec('PRAGMA foreign_keys = ON');

    $salt = \App\Infrastructure\Config::get('APP_SALT') ?: 'deterministic-salt';
    $hash = static fn(string $pw): string => hash('sha256', $pw . $salt);

    $pdo->prepare('INSERT INTO users (id,name,email,password_hash,role,status) VALUES (?,?,?,?,?,?)');
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Demo Admin', 'admin@example.com', $hash('Admin#2026'), 'admin', 'active']);
    $adminId = (int)$pdo->lastInsertId();
    $stmt->execute(['Demo User', 'user@example.com', $hash('User#2026'), 'user', 'active']);
    $userId = (int)$pdo->lastInsertId();
    $stmt->execute(['Demo Recipient', 'recipient@example.com', $hash('User#2026'), 'recipient', 'active']);
    $recipientId = (int)$pdo->lastInsertId();
    $stmt->execute(['Other Member', 'other@example.com', $hash('User#2026'), 'user', 'active']);
    $otherId = (int)$pdo->lastInsertId();

    $settingsDefaults = [
        'default_user_quota' => (int)(\App\Infrastructure\Config::get('DEFAULT_USER_QUOTA') ?: 104857600),
        'default_team_quota' => (int)(\App\Infrastructure\Config::get('DEFAULT_TEAM_QUOTA') ?: 1073741824),
        'retention_days' => (int)(\App\Infrastructure\Config::get('RETENTION_DAYS') ?: 30),
        'blocked_file_types' => 'exe,bat',
    ];
    $pdo->prepare('INSERT INTO admin_settings (id, default_user_quota, default_team_quota, retention_days, blocked_file_types, updated_by) VALUES (1, ?, ?, ?, ?, ?)')
        ->execute([
            $settingsDefaults['default_user_quota'],
            $settingsDefaults['default_team_quota'],
            $settingsDefaults['retention_days'],
            $settingsDefaults['blocked_file_types'],
            $adminId,
        ]);

    $upsertQuota = $pdo->prepare('INSERT INTO storage_quotas (user_id, limit_bytes, used_bytes) VALUES (?, ?, 0)');
    $upsertQuota->execute([$adminId, $settingsDefaults['default_user_quota']]);
    $upsertQuota->execute([$userId, $settingsDefaults['default_user_quota']]);
    $upsertQuota->execute([$recipientId, $settingsDefaults['default_user_quota']]);
    $upsertQuota->execute([$otherId, $settingsDefaults['default_user_quota']]);

    $insertFolder = $pdo->prepare('INSERT INTO folders (name, owner_id, team_id, parent_id, status) VALUES (?, ?, ?, NULL, \'active\')');
    $insertFolder->execute(['Admin Vault', $adminId, null]);
    $adminFolderId = (int)$pdo->lastInsertId();
    $insertFolder->execute(['User Workspace', $userId, null]);
    $userFolderId = (int)$pdo->lastInsertId();
    $insertFolder->execute(['Recipient Library', $recipientId, null]);
    $recipientFolderId = (int)$pdo->lastInsertId();
    $insertFolder->execute(['Other Workspace', $otherId, null]);
    $otherFolderId = (int)$pdo->lastInsertId();

    $insertTeam = $pdo->prepare('INSERT INTO team_spaces (name, description, created_by, root_folder_id, quota_limit, used_bytes) VALUES (?, ?, ?, ?, ?, 0)');
    $insertTeam->execute(['Platform Operations', 'Shared workspace for cross-functional teams', $adminId, null, $settingsDefaults['default_team_quota']]);
    $teamId = (int)$pdo->lastInsertId();
    $insertFolder->execute(['Platform Operations shared', null, $teamId]);
    $teamRootFolder = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE team_spaces SET root_folder_id = ? WHERE id = ?')->execute([$teamRootFolder, $teamId]);

    $insertMember = $pdo->prepare('INSERT INTO team_memberships (team_id, user_id, role, status) VALUES (?, ?, ?, \'active\')');
    $insertMember->execute([$teamId, $adminId, 'owner']);
    $insertMember->execute([$teamId, $userId, 'editor']);
    $insertMember->execute([$teamId, $recipientId, 'viewer']);
    $insertMember->execute([$teamId, $otherId, 'viewer']);

    $insertStored = $pdo->prepare('INSERT INTO stored_files (folder_id, owner_id, team_id, original_name, storage_name, description, mime_type, size, checksum, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertTag = $pdo->prepare('INSERT OR IGNORE INTO file_tags (file_id, tag) VALUES (?, ?)');
    $insertVersion = $pdo->prepare('INSERT INTO versions (file_id, version_number, stored_file_id, uploaded_by, size, change_summary) VALUES (?, ?, ?, ?, ?, ?)');

    $seedFile = function (string $name, string $content, ?int $folderId, ?int $ownerId, ?int $teamId, array $tags, string $mime = 'text/plain') use (&$pdo, $insertStored, $insertTag, $insertVersion) {
        $storage = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-z0-9.]/i', '_', $name);
        $path = \App\Infrastructure\Config::get('STORAGE_PATH') ?: 'var/storage';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $full = $path . DIRECTORY_SEPARATOR . $storage;
        file_put_contents($full, $content);
        $size = strlen($content);
        $checksum = hash('sha256', $content);
        $insertStored->execute([$folderId, $ownerId, $teamId, $name, $storage, 'Seed file: ' . $name, $mime, $size, $checksum, 'user_file', 'active']);
        $fileId = (int)$pdo->lastInsertId();
        foreach ($tags as $tag) {
            $insertTag->execute([$fileId, $tag]);
        }
        $insertVersion->execute([$fileId, 1, $fileId, $ownerId ?? 0, $size, 'initial seed']);
        $pdo->prepare('UPDATE storage_quotas SET used_bytes = used_bytes + ? WHERE user_id = ?')->execute([$size, $ownerId]);
        $pdo->prepare('UPDATE team_spaces SET used_bytes = used_bytes + ? WHERE id = ?')->execute([$size, $teamId]);
        $pdo->prepare('INSERT INTO file_uploads (file_id, uploaded_by, folder_id, original_name, description, tags, size, status) VALUES (?, ?, ?, ?, ?, ?, ?, \'completed\')')
            ->execute([$fileId, $ownerId, $folderId, $name, 'Seed upload', implode(',', $tags), $size]);
        return $fileId;
    };

    $adminFileId = $seedFile('README.md', "# Cloud File Sharing\nWelcome to the administrative vault.", $adminFolderId, $adminId, null, ['roadmap', 'admin'], 'text/markdown');
    $userFileId = $seedFile('roadmap.txt', "Initial product roadmap draft.", $userFolderId, $userId, null, ['roadmap'], 'text/plain');
    $seedFile('roadmap_v2.txt', "Updated product roadmap with Q4 deliverables.", $userFolderId, $userId, null, ['roadmap', 'plan'], 'text/plain');
    $insertVersion->execute([$userFileId, 2, $userFileId, $userId, strlen("Updated product roadmap with Q4 deliverables."), 'expanded scope']);
    $recipientFileId = $seedFile('notes.txt', "Recipient notes for upcoming meeting.", $recipientFolderId, $recipientId, null, ['notes'], 'text/plain');
    $teamFileId = $seedFile('welcome.txt', "Welcome to the Platform Operations shared folder.", $teamRootFolder, $adminId, $teamId, ['shared', 'welcome'], 'text/plain');
    $seedFile('other_plan.txt', "Confidential planning document owned by other member.", $otherFolderId, $otherId, null, ['confidential'], 'text/plain');

    $pdo->prepare('INSERT INTO trash_items (item_type, item_id, owner_id, team_id, original_name, original_parent_id, deleted_by, reason, purged_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NULL)')
        ->execute(['file', $recipientFileId, $recipientId, 'notes.txt', $recipientFolderId, $recipientId, 'user_deleted']);
    $pdo->prepare('UPDATE stored_files SET status = \'trashed\', deleted_at = datetime(\'now\') WHERE id = ?')->execute([$recipientFileId]);

    $publicStmt = $pdo->prepare('INSERT INTO shares (file_id, folder_id, created_by, token_selector, token_hash, scope, permission, expires_at, shared_with_user_id) VALUES (?, NULL, ?, ?, ?, ?, ?, NULL, NULL)');
    $publicToken = 'demo-public-share-2026';
    $publicHash = hash('sha256', $publicToken);
    $publicSelector = substr(hash('sha256', $publicToken . 'selector'), 0, 24);
    $publicStmt->execute([$teamFileId, $adminId, $publicSelector, $publicHash, 'public', 'download']);
    $privateStmt = $pdo->prepare('INSERT INTO shares (file_id, folder_id, created_by, token_selector, token_hash, scope, permission, expires_at, shared_with_user_id) VALUES (?, NULL, ?, ?, ?, ?, ?, NULL, ?)');
    $privateToken = 'demo-private-share-2026';
    $privateHash = hash('sha256', $privateToken);
    $privateSelector = substr(hash('sha256', $privateToken . 'selector'), 0, 24);
    $privateStmt->execute([$userFileId, $userId, $privateSelector, $privateHash, 'private', 'preview', $recipientId]);

    $insertSavedSearch = $pdo->prepare('INSERT INTO searches (user_id, name, query, owner, tag, from_date, to_date) VALUES (?, ?, ?, ?, ?, NULL, NULL)');
    $insertSavedSearch->execute([$userId, 'My roadmap files', 'roadmap', null, 'roadmap']);
    $insertSavedSearch->execute([$adminId, 'All admin files', null, 'admin@example.com', null]);

    $insertAudit = $pdo->prepare('INSERT INTO audit_events (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $insertAudit->execute([$adminId, 'seed', 'system', 0, json_encode(['note' => 'initial seed']), '127.0.0.1']);
    $insertAudit->execute([$userId, 'seed', 'system', 0, json_encode(['note' => 'initial seed']), '127.0.0.1']);

    $pdo->prepare('INSERT INTO account_access (user_id, action, status, details, ip_address) VALUES (?, ?, ?, ?, ?)')
        ->execute([$adminId, 'seed', 'success', json_encode(['note' => 'seed user']), '127.0.0.1']);
    $pdo->prepare('INSERT INTO folder_management (folder_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([$userFolderId, $userId, 'create', json_encode(['note' => 'seed folder'])]);
    $pdo->prepare('INSERT INTO version_history (version_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([1, $adminId, 'create', json_encode(['note' => 'seed version'])]);
    $pdo->prepare('INSERT INTO trash_log (trash_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([1, $recipientId, 'create', json_encode(['note' => 'seed trash'])]);
    $pdo->prepare('INSERT INTO storage_quota_log (quota_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([1, $userId, 'seed', json_encode(['note' => 'seed quota'])]);
    $pdo->prepare('INSERT INTO admin_console_log (setting_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([1, $adminId, 'seed', json_encode(['note' => 'seed settings'])]);
    $pdo->prepare('INSERT INTO audit_log_exports (export_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([0, $adminId, 'seed', json_encode(['note' => 'seed export'])]);
    $pdo->prepare('INSERT INTO sharing_links (share_id, user_id, action, success) VALUES (?, ?, ?, 1)')
        ->execute([1, $adminId, 'create']);
    $pdo->prepare('INSERT INTO team_spaces_log (team_id, user_id, action, success, details) VALUES (?, ?, ?, 1, ?)')
        ->execute([$teamId, $adminId, 'create', json_encode(['note' => 'seed team'])]);
    $pdo->prepare('INSERT INTO file_download_log (file_id, user_id, access_method, action, success) VALUES (?, ?, ?, ?, 1)')
        ->execute([$teamFileId, $adminId, 'seed', 'preview']);

    $pdo->prepare('INSERT INTO audit_exports (user_id, format, status, filters) VALUES (?, ?, ?, ?)')
        ->execute([$adminId, 'csv', 'ready', json_encode(['seed' => true])]);
}