<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Seeder
{
    public static function reset(string $root, string $dbPath, string $uploadDir): PDO
    {
        $database = str_starts_with($dbPath, '/') ? $dbPath : $root . '/' . $dbPath;
        $uploads = str_starts_with($uploadDir, '/') ? $uploadDir : $root . '/' . $uploadDir;
        if (is_file($database)) {
            unlink($database);
        }
        if (!is_dir($uploads)) {
            mkdir($uploads, 0775, true);
        }
        foreach (scandir($uploads) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.gitkeep' && is_file($uploads . '/' . $entry)) {
                unlink($uploads . '/' . $entry);
            }
        }
        $db = Database::connect($database);
        $db->exec((string)file_get_contents($root . '/database/schema.sql'));
        $hash = password_hash('Password123!', PASSWORD_DEFAULT);
        $users = [
            ['alice@example.test', $hash, 'Alice Owner', 'user', 'active', 10485760],
            ['bob@example.test', $hash, 'Bob Member', 'user', 'active', 10485760],
            ['disabled@example.test', $hash, 'Disabled User', 'user', 'disabled', 10485760],
            ['admin@example.test', $hash, 'Avery Admin', 'admin', 'active', 52428800],
        ];
        $statement = $db->prepare('INSERT INTO users(email,password_hash,name,role,status,quota_bytes) VALUES(?,?,?,?,?,?)');
        foreach ($users as $user) {
            $statement->execute($user);
        }
        foreach ([1 => 'Alice Files', 2 => 'Bob Files', 3 => 'Disabled Files', 4 => 'Admin Files'] as $owner => $name) {
            $db->prepare('INSERT INTO folders(name,owner_id) VALUES(?,?)')->execute([$name, $owner]);
        }
        $db->exec("INSERT INTO folders(name,owner_id) VALUES('Research Team Root',1)");
        $db->exec("INSERT INTO team_spaces(name,root_folder_id,created_by) VALUES('Research Team',5,1)");
        $db->exec('UPDATE folders SET team_space_id=1 WHERE id=5');
        $db->exec("INSERT INTO team_members(space_id,user_id,role) VALUES(1,1,'team_admin'),(1,2,'member')");
        $db->exec("INSERT INTO folders(name,parent_id,owner_id) VALUES('Documents',1,1)");
        $db->exec("INSERT INTO folders(name,parent_id,owner_id,team_space_id) VALUES('Project Alpha',5,1,1)");

        $fixtures = [
            ['seed-notes.blob', "Cloud file sharing notes\n", 'text/plain', 'notes.txt', 6, 1],
            ['seed-binary.blob', "\x00\x01binary", 'application/octet-stream', 'archive.bin', 1, 1],
            ['seed-team.blob', "Team plan version one\n", 'text/plain', 'team-plan.txt', 7, 1],
        ];
        foreach ($fixtures as $index => [$stored, $contents, $mime, $name, $folderId, $ownerId]) {
            file_put_contents($uploads . '/' . $stored, $contents);
            $db->prepare('INSERT INTO stored_blobs(stored_name,size_bytes,checksum) VALUES(?,?,?)')->execute([$stored, strlen($contents), hash('sha256', $contents)]);
            $blobId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO files(folder_id,owner_id,name,mime_type) VALUES(?,?,?,?)')->execute([$folderId, $ownerId, $name, $mime]);
            $fileId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO file_versions(file_id,blob_id,version_number,created_by) VALUES(?,?,1,?)')->execute([$fileId, $blobId, $ownerId]);
            $versionId = (int)$db->lastInsertId();
            $db->prepare('UPDATE files SET current_version_id=? WHERE id=?')->execute([$versionId, $fileId]);
        }
        $db->exec("INSERT INTO shares(token,file_id,permission,expires_at,created_by) VALUES('seed-view-token',1,'view','2035-01-01T00:00:00Z',1)");
        $db->exec("INSERT INTO audit_events(team_space_id,actor_id,action,entity_type,entity_id,details) VALUES(1,1,'space_created','team_space',1,'Research Team'),(1,1,'file_uploaded','file',3,'team-plan.txt')");
        $db->exec('INSERT INTO retention_settings(id,trash_days) VALUES(1,30)');
        return $db;
    }
}
