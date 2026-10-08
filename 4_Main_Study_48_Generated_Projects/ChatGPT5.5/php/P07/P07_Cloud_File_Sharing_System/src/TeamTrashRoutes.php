<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

final class TeamTrashRoutes
{
    use Support;

    public static function register(App $app, PDO $db, Auth $auth, CloudRepository $repo, array $config): void
    {
        $app->post('/api/team-spaces', function (Request $request, Response $response) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $data = self::body($request);
            self::required($data, ['name']);
            $db->beginTransaction();
            try {
                $db->prepare('INSERT INTO folders(name,owner_id) VALUES(?,?)')->execute([trim($data['name']) . ' Root', $user['id']]);
                $folderId = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO team_spaces(name,root_folder_id,created_by) VALUES(?,?,?)')->execute([trim($data['name']), $folderId, $user['id']]);
                $spaceId = (int)$db->lastInsertId();
                $db->prepare('UPDATE folders SET team_space_id=? WHERE id=?')->execute([$spaceId, $folderId]);
                $db->prepare("INSERT INTO team_members(space_id,user_id,role) VALUES(?,?,'team_admin')")->execute([$spaceId, $user['id']]);
                $db->commit();
            } catch (\PDOException) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw new ApiException(409, 'team_space_exists', 'A team space with that name already exists.');
            }
            $repo->audit($spaceId, (int)$user['id'], 'space_created', 'team_space', $spaceId, trim($data['name']));
            return Http::json($response, ['teamSpace' => self::space($db, $spaceId)], 201);
        });

        $app->get('/api/team-spaces', function (Request $request, Response $response) use ($auth, $db) {
            $user = $auth->requireUser($request);
            if ($user['role'] === 'admin') {
                $items = self::all($db, 'SELECT ts.*,tm.role member_role FROM team_spaces ts LEFT JOIN team_members tm ON tm.space_id=ts.id AND tm.user_id=? ORDER BY ts.name,ts.id', [$user['id']]);
            } else {
                $items = self::all($db, 'SELECT ts.*,tm.role member_role FROM team_spaces ts JOIN team_members tm ON tm.space_id=ts.id WHERE tm.user_id=? ORDER BY ts.name,ts.id', [$user['id']]);
            }
            return Http::json($response, ['items' => $items]);
        });

        $app->post('/api/team-spaces/{spaceId}/members', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $actor = $auth->requireUser($request);
            $space = $repo->requireTeamAdmin($actor, (int)$args['spaceId']);
            $data = self::body($request);
            self::required($data, ['userId','role']);
            if (!in_array($data['role'], ['member','team_admin'], true)) {
                throw new ApiException(422, 'validation_failed', 'Member role is invalid.', ['role' => 'Use member or team_admin.']);
            }
            self::one($db, "SELECT id FROM users WHERE id=? AND status='active'", [(int)$data['userId']], 'user_not_found');
            try {
                $db->prepare('INSERT INTO team_members(space_id,user_id,role) VALUES(?,?,?)')->execute([$space['id'], (int)$data['userId'], $data['role']]);
            } catch (\PDOException) {
                throw new ApiException(409, 'member_exists', 'The user is already a team member.');
            }
            $repo->audit((int)$space['id'], (int)$actor['id'], 'member_added', 'user', (int)$data['userId'], $data['role']);
            return Http::json($response, ['member' => self::one($db, 'SELECT * FROM team_members WHERE space_id=? AND user_id=?', [$space['id'], (int)$data['userId']])], 201);
        });

        $app->patch('/api/team-spaces/{spaceId}/members/{userId}', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $actor = $auth->requireUser($request);
            $space = $repo->requireTeamAdmin($actor, (int)$args['spaceId']);
            $member = self::one($db, 'SELECT * FROM team_members WHERE space_id=? AND user_id=?', [$space['id'], (int)$args['userId']], 'member_not_found');
            $data = self::body($request);
            self::required($data, ['role']);
            if (!in_array($data['role'], ['member','team_admin'], true)) {
                throw new ApiException(422, 'validation_failed', 'Member role is invalid.', ['role' => 'Use member or team_admin.']);
            }
            if ($member['role'] === 'team_admin' && $data['role'] === 'member') {
                $count = (int)self::one($db, "SELECT COUNT(*) count FROM team_members WHERE space_id=? AND role='team_admin'", [$space['id']])['count'];
                if ($count <= 1) {
                    throw new ApiException(409, 'last_team_admin', 'A team space must retain an administrator.');
                }
            }
            $db->prepare('UPDATE team_members SET role=? WHERE space_id=? AND user_id=?')->execute([$data['role'], $space['id'], $member['user_id']]);
            return Http::json($response, ['member' => self::one($db, 'SELECT * FROM team_members WHERE space_id=? AND user_id=?', [$space['id'], $member['user_id']])]);
        });

        $app->delete('/api/team-spaces/{spaceId}/members/{userId}', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $actor = $auth->requireUser($request);
            $space = $repo->requireTeamAdmin($actor, (int)$args['spaceId']);
            $member = self::one($db, 'SELECT * FROM team_members WHERE space_id=? AND user_id=?', [$space['id'], (int)$args['userId']], 'member_not_found');
            if ($member['role'] === 'team_admin') {
                $count = (int)self::one($db, "SELECT COUNT(*) count FROM team_members WHERE space_id=? AND role='team_admin'", [$space['id']])['count'];
                if ($count <= 1) {
                    throw new ApiException(409, 'last_team_admin', 'A team space must retain an administrator.');
                }
            }
            $db->prepare('DELETE FROM team_members WHERE space_id=? AND user_id=?')->execute([$space['id'], $member['user_id']]);
            $repo->audit((int)$space['id'], (int)$actor['id'], 'member_removed', 'user', (int)$member['user_id']);
            return $response->withStatus(204);
        });

        $app->get('/api/trash', function (Request $request, Response $response) use ($auth, $db) {
            $user = $auth->requireUser($request);
            $items = self::all($db, "SELECT t.*,CASE WHEN t.item_type='file' THEN (SELECT name FROM files WHERE id=t.item_id) ELSE (SELECT name FROM folders WHERE id=t.item_id) END name FROM trash_items t WHERE t.owner_id=? ORDER BY t.deleted_at DESC,t.id DESC", [$user['id']]);
            return Http::json($response, ['items' => $items]);
        });

        foreach (['files' => 'file', 'folders' => 'folder'] as $plural => $kind) {
            $app->post('/api/' . $plural . '/{' . $kind . 'Id}/trash', function (Request $request, Response $response, array $args) use ($auth, $db, $repo, $kind) {
                $user = $auth->requireUser($request);
                $id = (int)$args[$kind . 'Id'];
                $resource = $kind === 'file' ? $repo->requireFile($user, $id, true) : $repo->requireFolder($user, $id, true);
                if ($kind === 'folder' && $resource['parent_id'] === null) {
                    throw new ApiException(409, 'root_folder_protected', 'Root folders cannot be trashed.');
                }
                $parentId = $kind === 'file' ? (int)$resource['folder_id'] : (int)$resource['parent_id'];
                $column = $kind === 'file' ? 'files' : 'folders';
                $db->beginTransaction();
                $db->prepare("UPDATE $column SET trashed_at=datetime('now') WHERE id=?")->execute([$id]);
                $db->prepare('INSERT INTO trash_items(item_type,item_id,owner_id,original_parent_id) VALUES(?,?,?,?)')->execute([$kind, $id, $user['id'], $parentId]);
                $trashId = (int)$db->lastInsertId();
                $db->commit();
                return Http::json($response, ['trashItem' => self::one($db, 'SELECT * FROM trash_items WHERE id=?', [$trashId])], 201);
            });
        }

        $app->post('/api/trash/{itemId}/restore', function (Request $request, Response $response, array $args) use ($auth, $db) {
            $user = $auth->requireUser($request);
            $item = self::one($db, 'SELECT * FROM trash_items WHERE id=? AND owner_id=?', [(int)$args['itemId'], $user['id']], 'trash_item_not_found');
            $table = $item['item_type'] === 'file' ? 'files' : 'folders';
            $db->beginTransaction();
            $db->prepare("UPDATE $table SET trashed_at=NULL WHERE id=?")->execute([$item['item_id']]);
            $db->prepare('DELETE FROM trash_items WHERE id=?')->execute([$item['id']]);
            $db->commit();
            return Http::json($response, ['restored' => ['type' => $item['item_type'], 'id' => (int)$item['item_id']]]);
        });

        $app->delete('/api/trash/{itemId}', function (Request $request, Response $response, array $args) use ($auth, $db, $repo, $config) {
            $user = $auth->requireUser($request);
            $item = self::one($db, 'SELECT * FROM trash_items WHERE id=? AND owner_id=?', [(int)$args['itemId'], $user['id']], 'trash_item_not_found');
            if ($item['item_type'] === 'file') {
                $blobs = self::all($db, 'SELECT b.* FROM stored_blobs b JOIN file_versions v ON v.blob_id=b.id WHERE v.file_id=?', [$item['item_id']]);
                $db->beginTransaction();
                $db->prepare('DELETE FROM files WHERE id=?')->execute([$item['item_id']]);
                $db->prepare('DELETE FROM trash_items WHERE id=?')->execute([$item['id']]);
                foreach ($blobs as $blob) {
                    $db->prepare('DELETE FROM stored_blobs WHERE id=?')->execute([$blob['id']]);
                }
                $db->commit();
                foreach ($blobs as $blob) {
                    $path = $config['uploadDir'] . '/' . $blob['stored_name'];
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            } else {
                $children = (int)self::one($db, 'SELECT (SELECT COUNT(*) FROM folders WHERE parent_id=?)+(SELECT COUNT(*) FROM files WHERE folder_id=?) count', [$item['item_id'], $item['item_id']])['count'];
                if ($children > 0) {
                    throw new ApiException(409, 'folder_not_empty', 'A non-empty folder cannot be permanently deleted.');
                }
                $db->beginTransaction();
                $db->prepare('DELETE FROM folders WHERE id=?')->execute([$item['item_id']]);
                $db->prepare('DELETE FROM trash_items WHERE id=?')->execute([$item['id']]);
                $db->commit();
            }
            return $response->withStatus(204);
        });

        foreach (['json','csv'] as $format) {
            $path = '/api/team-spaces/{spaceId}/audit-events' . ($format === 'csv' ? '.csv' : '');
            $app->get($path, function (Request $request, Response $response, array $args) use ($auth, $db, $repo, $format) {
                $user = $auth->requireUser($request);
                $space = $repo->requireTeamAdmin($user, (int)$args['spaceId']);
                $rows = self::all($db, 'SELECT a.id,a.action,a.entity_type,a.entity_id,a.details,a.created_at,u.name actor_name FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id WHERE a.team_space_id=? ORDER BY a.created_at DESC,a.id DESC', [$space['id']]);
                if ($format === 'json') {
                    return Http::json($response, ['items' => $rows]);
                }
                $stream = fopen('php://temp', 'r+');
                $headers = ['id','action','entity_type','entity_id','details','created_at','actor_name'];
                fputcsv($stream, $headers);
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(fn(string $header) => $row[$header] ?? '', $headers));
                }
                rewind($stream);
                $response->getBody()->write((string)stream_get_contents($stream));
                return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="team-space-' . $space['id'] . '-audit.csv"');
            });
        }
    }

    private static function space(PDO $db, int $id): array
    {
        $space = self::one($db, 'SELECT * FROM team_spaces WHERE id=?', [$id], 'team_space_not_found');
        $space['members'] = self::all($db, 'SELECT tm.user_id,tm.role,u.name,u.email FROM team_members tm JOIN users u ON u.id=tm.user_id WHERE tm.space_id=? ORDER BY u.name,u.id', [$id]);
        return $space;
    }
}
