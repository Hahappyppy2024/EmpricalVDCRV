<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

final class FileFolderRoutes
{
    use Support;

    public static function register(App $app, PDO $db, Auth $auth, CloudRepository $repo): void
    {
        $app->get('/api/folders/{folderId}/children', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $folder = $repo->requireFolder($user, (int)$args['folderId']);
            $folders = self::all($db, 'SELECT id,name,parent_id,owner_id,team_space_id,version,created_at,updated_at FROM folders WHERE parent_id=? AND trashed_at IS NULL ORDER BY name,id', [$folder['id']]);
            $files = self::all($db, 'SELECT id,name,mime_type,owner_id,version,modified_at FROM files WHERE folder_id=? AND trashed_at IS NULL ORDER BY name,id', [$folder['id']]);
            return Http::json($response, ['folder' => $folder, 'folders' => $folders, 'files' => $files]);
        });

        $app->post('/api/folders', function (Request $request, Response $response) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $data = self::body($request);
            self::required($data, ['name','parentId']);
            $parent = $repo->requireFolder($user, (int)$data['parentId'], true);
            try {
                $db->prepare('INSERT INTO folders(name,parent_id,owner_id,team_space_id) VALUES(?,?,?,?)')
                    ->execute([trim($data['name']), $parent['id'], $user['id'], $parent['team_space_id']]);
            } catch (\PDOException) {
                throw new ApiException(409, 'folder_exists', 'A folder with that name already exists here.');
            }
            $id = (int)$db->lastInsertId();
            $repo->audit($parent['team_space_id'] === null ? null : (int)$parent['team_space_id'], (int)$user['id'], 'folder_created', 'folder', $id, trim($data['name']));
            return Http::json($response, ['folder' => $repo->folder($id)], 201);
        });

        $app->patch('/api/folders/{folderId}', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $folder = $repo->requireFolder($user, (int)$args['folderId'], true);
            $data = self::body($request);
            self::required($data, ['name','version']);
            if ((int)$data['version'] !== (int)$folder['version']) {
                throw new ApiException(409, 'stale_version', 'Folder changed in another request.');
            }
            try {
                $db->prepare("UPDATE folders SET name=?,version=version+1,updated_at=datetime('now') WHERE id=?")
                    ->execute([trim($data['name']), $folder['id']]);
            } catch (\PDOException) {
                throw new ApiException(409, 'folder_exists', 'A folder with that name already exists here.');
            }
            return Http::json($response, ['folder' => $repo->folder((int)$folder['id'])]);
        });

        $app->post('/api/folders/{folderId}/move', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $folder = $repo->requireFolder($user, (int)$args['folderId'], true);
            $data = self::body($request);
            self::required($data, ['targetFolderId','version']);
            if ((int)$data['version'] !== (int)$folder['version']) {
                throw new ApiException(409, 'stale_version', 'Folder changed in another request.');
            }
            $target = $repo->requireFolder($user, (int)$data['targetFolderId'], true);
            if ((int)$target['id'] === (int)$folder['id']) {
                throw new ApiException(409, 'invalid_folder_move', 'A folder cannot contain itself.');
            }
            $cursor = $target;
            while ($cursor['parent_id'] !== null) {
                if ((int)$cursor['parent_id'] === (int)$folder['id']) {
                    throw new ApiException(409, 'invalid_folder_move', 'A folder cannot move into its descendant.');
                }
                $cursor = $repo->folder((int)$cursor['parent_id']);
            }
            if ($folder['team_space_id'] !== $target['team_space_id']) {
                throw new ApiException(409, 'cross_space_move_denied', 'Folders cannot move across personal and team roots.');
            }
            $db->prepare("UPDATE folders SET parent_id=?,version=version+1,updated_at=datetime('now') WHERE id=?")
                ->execute([$target['id'], $folder['id']]);
            return Http::json($response, ['folder' => $repo->folder((int)$folder['id'])]);
        });

        $app->delete('/api/folders/{folderId}', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $folder = $repo->requireFolder($user, (int)$args['folderId'], true);
            if ($folder['parent_id'] === null) {
                throw new ApiException(409, 'root_folder_protected', 'Root folders cannot be deleted.');
            }
            $count = self::one($db, 'SELECT (SELECT COUNT(*) FROM folders WHERE parent_id=?)+(SELECT COUNT(*) FROM files WHERE folder_id=?) count', [$folder['id'], $folder['id']]);
            if ((int)$count['count'] > 0) {
                throw new ApiException(409, 'folder_not_empty', 'Only empty folders can be deleted directly.');
            }
            $db->prepare('DELETE FROM folders WHERE id=?')->execute([$folder['id']]);
            return $response->withStatus(204);
        });

        $app->post('/api/folders/{folderId}/files', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $folder = $repo->requireFolder($user, (int)$args['folderId'], true);
            [$upload, $name, $mime, $contents] = self::upload($request);
            $repo->ensureQuota((int)$user['id'], strlen($contents));
            $db->beginTransaction();
            $blob = null;
            try {
                $blob = $repo->storeBlob($contents);
                $db->prepare('INSERT INTO files(folder_id,owner_id,name,mime_type) VALUES(?,?,?,?)')->execute([$folder['id'], $user['id'], $name, $mime]);
                $fileId = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO file_versions(file_id,blob_id,version_number,created_by) VALUES(?,?,1,?)')->execute([$fileId, $blob['id'], $user['id']]);
                $versionId = (int)$db->lastInsertId();
                $db->prepare('UPDATE files SET current_version_id=? WHERE id=?')->execute([$versionId, $fileId]);
                $db->commit();
            } catch (\PDOException) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($blob !== null) {
                    $repo->discardBlobFile($blob);
                }
                throw new ApiException(409, 'file_exists', 'A file with that name already exists here.');
            }
            $repo->audit($folder['team_space_id'] === null ? null : (int)$folder['team_space_id'], (int)$user['id'], 'file_uploaded', 'file', $fileId, $name);
            return Http::json($response, ['file' => $repo->file($fileId), 'sizeBytes' => $upload->getSize()], 201);
        });

        $app->get('/api/files/{fileId}/content', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            [$file, $version] = self::contentVersion($request, $auth, $db, $repo, (int)$args['fileId'], null, true);
            [$blob, $contents] = $repo->blobContent((int)$version['blob_id']);
            $response->getBody()->write($contents);
            return $response->withHeader('Content-Type', $file['mime_type'])->withHeader('Content-Length', (string)$blob['size_bytes'])->withHeader('Content-Disposition', 'attachment; filename="' . str_replace('"', '', $file['name']) . '"');
        });
        $app->get('/api/files/{fileId}/preview', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            [$file, $version] = self::contentVersion($request, $auth, $db, $repo, (int)$args['fileId'], null, false);
            if (!str_starts_with($file['mime_type'], 'text/') && $file['mime_type'] !== 'application/json') {
                throw new ApiException(422, 'preview_unsupported', 'This file type cannot be previewed.');
            }
            [, $contents] = $repo->blobContent((int)$version['blob_id']);
            return Http::json($response, ['preview' => ['fileId' => (int)$file['id'], 'name' => $file['name'], 'mimeType' => $file['mime_type'], 'text' => $contents]]);
        });
        $app->get('/api/files/{fileId}/versions/{versionId}/content', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            [$file, $version] = self::contentVersion($request, $auth, $db, $repo, (int)$args['fileId'], (int)$args['versionId'], true);
            [, $contents] = $repo->blobContent((int)$version['blob_id']);
            $response->getBody()->write($contents);
            return $response->withHeader('Content-Type', $file['mime_type']);
        });

        foreach (['files' => 'file', 'folders' => 'folder'] as $plural => $kind) {
            $app->post('/api/' . $plural . '/{' . $kind . 'Id}/shares', function (Request $request, Response $response, array $args) use ($auth, $db, $repo, $kind) {
                $user = $auth->requireUser($request);
                $id = (int)$args[$kind . 'Id'];
                $resource = $kind === 'file' ? $repo->requireFile($user, $id, true) : $repo->requireFolder($user, $id, true);
                $data = self::body($request);
                self::required($data, ['permission','expiresAt']);
                if (!in_array($data['permission'], ['view','download'], true)) {
                    throw new ApiException(422, 'validation_failed', 'Share permission is invalid.', ['permission' => 'Use view or download.']);
                }
                if (!strtotime($data['expiresAt']) || strtotime($data['expiresAt']) <= time()) {
                    throw new ApiException(422, 'validation_failed', 'Share expiry must be in the future.', ['expiresAt' => 'Use a future date.']);
                }
                $token = bin2hex(random_bytes(18));
                $db->prepare('INSERT INTO shares(token,file_id,folder_id,permission,expires_at,created_by) VALUES(?,?,?,?,?,?)')
                    ->execute([$token, $kind === 'file' ? $resource['id'] : null, $kind === 'folder' ? $resource['id'] : null, $data['permission'], $data['expiresAt'], $user['id']]);
                $share = self::one($db, 'SELECT * FROM shares WHERE id=?', [(int)$db->lastInsertId()]);
                return Http::json($response, ['share' => $share, 'url' => '/api/shares/' . $token], 201);
            });
        }

        $app->delete('/api/shares/{shareId}', function (Request $request, Response $response, array $args) use ($auth, $db) {
            $user = $auth->requireUser($request);
            $share = self::one($db, 'SELECT * FROM shares WHERE id=?', [(int)$args['shareId']], 'share_not_found');
            if ((int)$share['created_by'] !== (int)$user['id'] && $user['role'] !== 'admin') {
                throw new ApiException(403, 'share_access_denied', 'Only the share creator may revoke this link.');
            }
            $db->prepare("UPDATE shares SET revoked_at=COALESCE(revoked_at,datetime('now')) WHERE id=?")->execute([$share['id']]);
            return $response->withStatus(204);
        });

        $app->get('/api/shares/{token}', function (Request $request, Response $response, array $args) use ($db) {
            $share = self::one($db, "SELECT * FROM shares WHERE token=? AND revoked_at IS NULL AND expires_at>datetime('now')", [$args['token']], 'share_not_found');
            if ($share['file_id'] !== null) {
                $resource = self::one($db, 'SELECT id,name,mime_type,modified_at FROM files WHERE id=? AND trashed_at IS NULL', [$share['file_id']], 'share_not_found');
                $type = 'file';
            } else {
                $resource = self::one($db, 'SELECT id,name,updated_at FROM folders WHERE id=? AND trashed_at IS NULL', [$share['folder_id']], 'share_not_found');
                $type = 'folder';
            }
            return Http::json($response, ['share' => ['id' => (int)$share['id'], 'token' => $share['token'], 'permission' => $share['permission'], 'expiresAt' => $share['expires_at'], 'resourceType' => $type, 'resource' => $resource]]);
        });

        $app->get('/api/search/files', function (Request $request, Response $response) use ($auth, $db) {
            $user = $auth->requireUser($request);
            $query = $request->getQueryParams();
            [$limit, $offset, $page] = self::page($request);
            $where = ["f.trashed_at IS NULL", "fo.trashed_at IS NULL", "(f.owner_id=? OR EXISTS(SELECT 1 FROM team_members tm WHERE tm.space_id=fo.team_space_id AND tm.user_id=?))"];
            $params = [$user['id'], $user['id']];
            if (trim((string)($query['q'] ?? '')) !== '') {
                $where[] = '(f.name LIKE ? OR f.mime_type LIKE ?)';
                $term = '%' . trim($query['q']) . '%';
                $params[] = $term;
                $params[] = $term;
            }
            if (trim((string)($query['type'] ?? '')) !== '') {
                $where[] = 'f.mime_type LIKE ?';
                $params[] = trim($query['type']) . '%';
            }
            if (trim((string)($query['owner'] ?? '')) !== '') {
                $where[] = 'u.email=? COLLATE NOCASE';
                $params[] = trim($query['owner']);
            }
            if (trim((string)($query['modifiedAfter'] ?? '')) !== '') {
                if (!strtotime($query['modifiedAfter'])) {
                    throw new ApiException(422, 'validation_failed', 'Modified date is invalid.', ['modifiedAfter' => 'Use a valid date.']);
                }
                $where[] = 'f.modified_at>=?';
                $params[] = trim($query['modifiedAfter']);
            }
            $sql = 'SELECT f.id,f.name,f.mime_type,f.owner_id,u.email owner_email,fo.name folder_name,f.modified_at FROM files f JOIN folders fo ON fo.id=f.folder_id JOIN users u ON u.id=f.owner_id WHERE ' . implode(' AND ', $where) . ' ORDER BY f.modified_at DESC,f.id DESC LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
            return Http::json($response, ['items' => self::all($db, $sql, $params), 'page' => $page, 'limit' => $limit]);
        });

        $app->get('/api/files/{fileId}/versions', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $file = $repo->requireFile($user, (int)$args['fileId']);
            $items = self::all($db, 'SELECT v.id,v.version_number,v.created_at,b.size_bytes,b.checksum,u.name created_by_name FROM file_versions v JOIN stored_blobs b ON b.id=v.blob_id JOIN users u ON u.id=v.created_by WHERE v.file_id=? ORDER BY v.version_number DESC', [$file['id']]);
            return Http::json($response, ['file' => $file, 'items' => $items]);
        });

        $app->post('/api/files/{fileId}/versions', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $file = $repo->requireFile($user, (int)$args['fileId'], true);
            [, , $mime, $contents] = self::upload($request);
            $repo->ensureQuota((int)$file['owner_id'], strlen($contents));
            $number = (int)self::one($db, 'SELECT MAX(version_number) number FROM file_versions WHERE file_id=?', [$file['id']])['number'] + 1;
            $db->beginTransaction();
            $blob = null;
            try {
                $blob = $repo->storeBlob($contents);
                $db->prepare('INSERT INTO file_versions(file_id,blob_id,version_number,created_by) VALUES(?,?,?,?)')->execute([$file['id'], $blob['id'], $number, $user['id']]);
                $versionId = (int)$db->lastInsertId();
                $db->prepare("UPDATE files SET current_version_id=?,mime_type=?,version=version+1,modified_at=datetime('now') WHERE id=?")->execute([$versionId, $mime, $file['id']]);
                $db->commit();
            } catch (\Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($blob !== null) {
                    $repo->discardBlobFile($blob);
                }
                throw $exception;
            }
            return Http::json($response, ['version' => self::one($db, 'SELECT * FROM file_versions WHERE id=?', [$versionId]), 'file' => $repo->file((int)$file['id'])], 201);
        });

        $app->post('/api/files/{fileId}/versions/{versionId}/restore', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $user = $auth->requireUser($request);
            $file = $repo->requireFile($user, (int)$args['fileId'], true);
            $source = self::one($db, 'SELECT * FROM file_versions WHERE id=? AND file_id=?', [(int)$args['versionId'], $file['id']], 'version_not_found');
            if ((int)$file['current_version_id'] === (int)$source['id']) {
                throw new ApiException(409, 'version_already_current', 'That version is already current.');
            }
            $number = (int)self::one($db, 'SELECT MAX(version_number) number FROM file_versions WHERE file_id=?', [$file['id']])['number'] + 1;
            $db->beginTransaction();
            $db->prepare('INSERT INTO file_versions(file_id,blob_id,version_number,created_by) VALUES(?,?,?,?)')->execute([$file['id'], $source['blob_id'], $number, $user['id']]);
            $newId = (int)$db->lastInsertId();
            $db->prepare("UPDATE files SET current_version_id=?,version=version+1,modified_at=datetime('now') WHERE id=?")->execute([$newId, $file['id']]);
            $db->commit();
            return Http::json($response, ['version' => self::one($db, 'SELECT * FROM file_versions WHERE id=?', [$newId]), 'file' => $repo->file((int)$file['id'])], 201);
        });
    }

    private static function upload(Request $request): array
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['file']) || $files['file']->getError() !== UPLOAD_ERR_OK) {
            throw new ApiException(422, 'validation_failed', 'A file upload is required.', ['file' => 'Required.']);
        }
        $upload = $files['file'];
        if ((int)$upload->getSize() < 1) {
            throw new ApiException(422, 'validation_failed', 'The uploaded file is empty.', ['file' => 'Empty file.']);
        }
        $contents = (string)$upload->getStream();
        $name = trim((string)$upload->getClientFilename());
        if ($name === '') {
            $name = 'upload.bin';
        }
        $mime = trim((string)$upload->getClientMediaType());
        return [$upload, $name, $mime === '' ? 'application/octet-stream' : $mime, $contents];
    }

    private static function contentVersion(Request $request, Auth $auth, PDO $db, CloudRepository $repo, int $fileId, ?int $versionId, bool $download): array
    {
        $file = $repo->file($fileId);
        $shareToken = trim((string)($request->getQueryParams()['shareToken'] ?? ''));
        if ($shareToken !== '') {
            $share = self::one($db, "SELECT * FROM shares WHERE token=? AND revoked_at IS NULL AND expires_at>datetime('now')", [$shareToken], 'share_not_found');
            $folderMatch = false;
            if ($share['folder_id'] !== null) {
                $cursor = $repo->folder((int)$file['folder_id']);
                while (true) {
                    if ((int)$cursor['id'] === (int)$share['folder_id']) {
                        $folderMatch = true;
                        break;
                    }
                    if ($cursor['parent_id'] === null) {
                        break;
                    }
                    $cursor = $repo->folder((int)$cursor['parent_id']);
                }
            }
            if ((int)($share['file_id'] ?? 0) !== $fileId && !$folderMatch) {
                throw new ApiException(403, 'share_access_denied', 'The share does not grant access to this file.');
            }
            if ($download && $share['permission'] !== 'download') {
                throw new ApiException(403, 'share_download_denied', 'This link allows preview only.');
            }
        } else {
            $user = $auth->requireUser($request);
            $repo->requireFile($user, $fileId);
        }
        $version = $versionId === null
            ? self::one($db, 'SELECT * FROM file_versions WHERE id=?', [$file['current_version_id']], 'version_not_found')
            : self::one($db, 'SELECT * FROM file_versions WHERE id=? AND file_id=?', [$versionId, $fileId], 'version_not_found');
        return [$file, $version];
    }
}
