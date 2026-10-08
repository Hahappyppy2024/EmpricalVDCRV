<?php

declare(strict_types=1);

namespace CloudFS\Database;

use CloudFS\Database\Database;
use CloudFS\Services\StorageService;
use CloudFS\Services\AuditService;

final class Seeder
{
    public function __construct(
        private Database $db,
        private StorageService $storage,
        private AuditService $audit
    ) {
    }

    public function seed(): array
    {
        $admin = $this->createUser('admin', 'admin@example.test', 'Admin One', 'admin', 'admin123', 1073741824);
        $alice = $this->createUser('alice', 'alice@example.test', 'Alice Anderson', 'user', 'password123', 262144000);
        $bob   = $this->createUser('bob', 'bob@example.test', 'Bob Baker', 'user', 'password123', 262144000);

        $this->seedSettings($admin['id']);

        $aliceDocs = $this->db->insert(
            'INSERT INTO folders (owner_id, parent_id, name) VALUES (?, NULL, ?)',
            [$alice['id'], 'Documents']
        );
        $aliceShared = $this->db->insert(
            'INSERT INTO folders (owner_id, parent_id, name) VALUES (?, NULL, ?)',
            [$alice['id'], 'Shared']
        );
        $bobDocs = $this->db->insert(
            'INSERT INTO folders (owner_id, parent_id, name) VALUES (?, NULL, ?)',
            [$bob['id'], 'Documents']
        );

        $reportId = $this->seedFile($alice['id'], $aliceDocs, 'Q3_Report.txt', 'text/plain', 24576, 'Quarterly sales report', 'report,finance', $admin['id']);
        $photoId  = $this->seedFile($alice['id'], $aliceDocs, 'Team_Photo.bin', 'application/octet-stream', 1048576, 'Team retreat photo', 'photo,team', $admin['id']);
        $notesId  = $this->seedFile($alice['id'], $aliceShared, 'Meeting_Notes.txt', 'text/plain', 8192, 'Shared meeting notes', 'notes,meeting', $admin['id']);
        $bobPlan  = $this->seedFile($bob['id'], $bobDocs, 'Project_Plan.txt', 'text/plain', 12288, 'Personal project plan', 'plan,project', $admin['id']);

        $this->seedVersions($reportId, $alice['id'], $admin['id']);
        $this->seedVersions($notesId, $alice['id'], $admin['id']);

        $this->seedTrash($bobPlan, $bob['id'], $bobDocs, $admin['id']);

        $this->seedShares($alice['id'], $notesId, $admin['id']);

        $teamId = $this->seedTeam('Marketing', 'Shared marketing material', $alice['id'], [$alice['id'], $bob['id'], $admin['id']]);

        $this->seedQuota($admin['id'], $alice['id'], $bob['id']);

        $this->seedBlockedTypes($admin['id']);

        $this->seedAudit($admin['id'], $alice['id'], $bob['id'], $aliceDocs, $reportId, $teamId);

        return ['admin' => $admin['username'], 'alice' => $alice['username'], 'bob' => $bob['username']];
    }

    private function createUser(string $username, string $email, string $fullName, string $role, string $password, int $quota): array
    {
        $existing = $this->db->one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($existing) {
            return $existing;
        }
        $id = $this->db->insert(
            'INSERT INTO users (username, email, password_hash, full_name, role, quota_bytes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role, $quota]
        );
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    private function seedSettings(int $adminId): void
    {
        $settings = [
            'default_quota_bytes' => '104857600',
            'max_upload_bytes' => '52428800',
            'trash_retention_days' => '30',
            'version_retention_count' => '10',
            'maintenance_mode' => '0',
            'allow_registration' => '1',
        ];
        foreach ($settings as $key => $value) {
            $this->db->run(
                'INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)',
                [$key, $value]
            );
        }
        $this->db->run('INSERT OR IGNORE INTO audit_events (user_id, actor_name, action, target_type, target_id, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$adminId, 'admin', 'admin.settings.seeded', 'settings', 'defaults', '{"seeded":true}']);
    }

    private function seedFile(int $ownerId, int $folderId, string $name, string $mime, int $size, string $description, string $tags, int $auditorId): int
    {
        $contents = $this->seedContents($name, $size);
        $stored = $this->storage->store($contents, $name);
        $id = $this->db->insert(
            'INSERT INTO files (owner_id, folder_id, name, original_name, storage_path, mime_type, size_bytes, description, tags, status, current_version, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', 1, datetime(\'now\'), datetime(\'now\'))',
            [$ownerId, $folderId, $name, $name, $stored['storage_path'], $mime, $stored['size_bytes'], $description, $tags]
        );
        $this->db->insert(
            'INSERT INTO file_versions (file_id, version_number, storage_path, size_bytes, mime_type, uploaded_by, comment, created_at) VALUES (?, 1, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$id, $stored['storage_path'], $stored['size_bytes'], $mime, $ownerId, 'Initial upload']
        );
        $this->audit->log($auditorId, 'seed.file.created', 'file', (string) $id, ['name' => $name, 'owner' => $ownerId]);
        return $id;
    }

    private function seedContents(string $name, int $size): string
    {
        $lines = [];
        $lines[] = 'Synthetic seed file: ' . $name;
        $lines[] = 'Generated deterministically by the P07 Cloud File-Sharing System seeder.';
        $lines[] = 'Line ' . str_repeat('-', 60);
        for ($i = 1; $i <= 40; $i++) {
            $lines[] = sprintf('seed-line %03d :: %s', $i, str_repeat(substr($name, 0, 1), 40));
        }
        $base = implode(PHP_EOL, $lines);
        while (strlen($base) < $size) {
            $base .= PHP_EOL . 'padding:' . $name;
        }
        return substr($base, 0, $size);
    }

    private function seedVersions(int $fileId, int $uploaderId, int $auditorId): void
    {
        $file = $this->db->one('SELECT * FROM files WHERE id = ?', [$fileId]);
        if (!$file) {
            return;
        }
        for ($v = 2; $v <= 3; $v++) {
            $stored = $this->storage->store(
                $this->seedContents($file['name'] . '.v' . $v, $file['size_bytes'] + ($v * 512)),
                $file['name']
            );
            $this->db->insert(
                'INSERT INTO file_versions (file_id, version_number, storage_path, size_bytes, mime_type, uploaded_by, comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\', \'-' . (4 - $v) . ' days\'))',
                [$fileId, $v, $stored['storage_path'], $stored['size_bytes'], $file['mime_type'], $uploaderId, 'Revision ' . $v]
            );
        }
        $this->db->run('UPDATE files SET current_version = 3, updated_at = datetime(\'now\') WHERE id = ?', [$fileId]);
        $this->audit->log($auditorId, 'version.history.seeded', 'file', (string) $fileId, ['versions' => 3]);
    }

    private function seedTrash(int $fileId, int $userId, int $folderId, int $auditorId): void
    {
        $this->db->run("UPDATE files SET status = 'trashed', trashed_at = datetime('now'), deleted_at = datetime('now', '+60 days') WHERE id = ?", [$fileId]);
        $this->db->insert('INSERT INTO trash (file_id, original_folder_id, trashed_by, trashed_at) VALUES (?, ?, ?, datetime(\'now\'))', [$fileId, $folderId, $userId]);
        $this->audit->log($auditorId, 'trash.file.deleted', 'file', (string) $fileId, ['action' => 'to_trash']);
    }

    private function seedShares(int $ownerId, int $fileId, int $auditorId): void
    {
        $this->db->insert(
            'INSERT INTO shares (file_id, owner_id, token, scope, permissions, expires_at, created_at) VALUES (?, ?, ?, \'public\', \'download\', datetime(\'now\', \'+30 days\'), datetime(\'now\'))',
            [$fileId, $ownerId, 'seedshare' . $fileId . 'a1b2']
        );
        $this->db->insert(
            'INSERT INTO shares (file_id, owner_id, token, scope, password_hash, permissions, expires_at, created_at) VALUES (?, ?, ?, \'private\', ?, \'view\', datetime(\'now\', \'+7 days\'), datetime(\'now\'))',
            [$fileId, $ownerId, 'privateshare' . $fileId . 'c3d4', password_hash('secret123', PASSWORD_DEFAULT)]
        );
        $this->audit->log($auditorId, 'share.created', 'share', (string) $fileId, ['owner' => $ownerId]);
    }

    private function seedTeam(string $name, string $description, int $ownerId, array $memberIds): int
    {
        $teamId = $this->db->insert(
            'INSERT INTO teams (name, description, created_by, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
            [$name, $description, $ownerId]
        );
        foreach ($memberIds as $i => $uid) {
            $this->db->insert(
                'INSERT INTO team_members (team_id, user_id, role, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
                [$teamId, $uid, $i === 0 ? 'owner' : 'member']
            );
        }
        $this->db->insert('INSERT INTO team_folders (team_id, folder_id, created_at) VALUES (?, (SELECT id FROM folders WHERE owner_id = ? AND name = \'Documents\' LIMIT 1), datetime(\'now\'))', [$teamId, $ownerId]);
        $this->audit->log($ownerId, 'team.created', 'team', (string) $teamId, ['name' => $name, 'members' => count($memberIds)]);
        return $teamId;
    }

    private function seedQuota(int $adminId, int $aliceId, int $bobId): void
    {
        $this->db->insert('INSERT OR IGNORE INTO storage_quota (user_id, used_bytes, soft_limit, hard_limit) VALUES (?, 0, NULL, NULL)', [$adminId]);
        $this->db->insert('INSERT OR IGNORE INTO storage_quota (user_id, used_bytes, soft_limit, hard_limit) VALUES (?, 1572864, 204800000, 262144000)', [$aliceId]);
        $this->db->insert('INSERT OR IGNORE INTO storage_quota (user_id, used_bytes, soft_limit, hard_limit) VALUES (?, 12288, 204800000, 262144000)', [$bobId]);
    }

    private function seedBlockedTypes(int $adminId): void
    {
        $blocked = [
            ['exe', 'executables are blocked by storage policy'],
            ['bat', 'script files are blocked by storage policy'],
            ['php', 'server-side code is blocked by storage policy'],
        ];
        foreach ($blocked as $row) {
            $this->db->run(
                'INSERT OR IGNORE INTO blocked_file_types (extension, reason, created_by, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
                [$row[0], $row[1], $adminId]
            );
        }
    }

    private function seedAudit(int $adminId, int $aliceId, int $bobId, int $aliceDocs, int $reportId, int $teamId): void
    {
        $rows = [
            [$aliceId, 'alice', 'file.uploaded', 'file', (string) $reportId, '{"name":"Q3_Report.txt"}'],
            [$aliceId, 'alice', 'folder.created', 'folder', (string) $aliceDocs, '{"name":"Documents"}'],
            [$bobId, 'bob', 'login', 'session', null, '{}'],
            [$aliceId, 'alice', 'share.created', 'share', (string) $reportId, '{"scope":"public"}'],
            [$bobId, 'bob', 'file.downloaded', 'file', (string) $reportId, '{"via":"share"}'],
            [$adminId, 'admin', 'admin.policy.updated', 'policy', 'blocked_file_types', '{"extensions":3}'],
            [$adminId, 'admin', 'admin.user.updated', 'user', (string) $aliceId, '{"field":"quota_bytes"}'],
            [$aliceId, 'alice', 'team.member_added', 'team', (string) $teamId, '{"member":"bob"}'],
            [$bobId, 'bob', 'trash.purged', 'file', null, '{}'],
            [$aliceId, 'alice', 'export.created', 'audit', 'csv', '{"rows":2}'],
        ];
        foreach ($rows as $r) {
            $this->db->insert(
                'INSERT INTO audit_events (user_id, actor_name, action, target_type, target_id, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\', \'-' . random_int(0, 20) . ' days\'))',
                $r
            );
        }
    }
}
