<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\StorageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class DownloadPreviewController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private StorageService $storage,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $records = $this->db->all(
            'SELECT f.id, f.name, f.original_name, f.mime_type, f.size_bytes, f.created_at, f.tags,
                    (SELECT COUNT(*) FROM file_versions v WHERE v.file_id = f.id) AS version_count,
                    (SELECT COUNT(*) FROM shares s WHERE s.file_id = f.id AND s.revoked = 0) AS share_count
             FROM files f WHERE f.owner_id = ? AND f.status = \'active\' ORDER BY f.name',
            [(int) $user['id']]
        );
        return $this->json($response, ['ok' => true, 'records' => $records]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fileId = (int) ($data['file_id'] ?? 0);
        $file = $this->files->findFile($fileId);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $action = (string) ($data['action'] ?? 'download');
        if (!in_array($action, ['download', 'preview'], true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Unknown file operation.']], 422);
        }
        $this->audit->log((int) $user['id'], 'file.' . $action . 'ed', 'file', (string) $fileId, ['name' => $file['name']]);
        return $this->json($response, [
            'ok' => true,
            'id' => $fileId,
            'name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'action' => $action,
            'url' => '/files/' . $fileId . ($action === 'download' ? '/download' : '/preview'),
            'message' => 'File ' . $action . ' recorded.',
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $file = $this->files->findFile((int) $args['id']);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id']) {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        return $this->json($response, ['ok' => true, 'id' => (int) $args['id'], 'message' => 'File access record updated.']);
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $file = $this->files->findFile((int) $args['id']);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id'] || $file['status'] !== 'active') {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $content = $this->storage->read($file['storage_path']);
        $this->audit->log((int) $user['id'], 'file.downloaded', 'file', (string) $file['id'], ['name' => $file['name']]);
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($file['name']) . '"')
            ->withHeader('Content-Length', (string) strlen($content));
    }

    public function preview(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $file = $this->files->findFile((int) $args['id']);
        if (!$file || (int) $file['owner_id'] !== (int) $user['id'] || $file['status'] !== 'active') {
            return $this->json($response, ['ok' => false, 'errors' => ['File not found or out of scope.']], 404);
        }
        $this->audit->log((int) $user['id'], 'file.previewed', 'file', (string) $file['id'], ['name' => $file['name']]);
        return $this->view->render($response, 'preview.php', [
            'current_user' => $user,
            'file' => $file,
            'content' => $this->previewText($file),
        ]);
    }

    public function sharePage(Request $request, Response $response, array $args): Response
    {
        $share = $this->files->findShareByToken((string) $args['token']);
        if (!$share) {
            return $this->renderShareError($response, 'This share link is invalid or has been revoked.');
        }
        if ($share['expires_at'] !== null && $share['expires_at'] < gmdate('Y-m-d H:i:s')) {
            return $this->renderShareError($response, 'This share link has expired.');
        }
        if ($share['scope'] === 'private') {
            $password = $request->getQueryParams()['password'] ?? null;
            if ($password === null) {
                return $this->renderSharePassword($response, $share);
            }
            if (!$share['password_hash'] || !password_verify((string) $password, $share['password_hash'])) {
                return $this->renderSharePassword($response, $share, 'Invalid share password.');
            }
        }
        return $this->renderShareView($response, $share);
    }

    public function shareDownload(Request $request, Response $response, array $args): Response
    {
        $share = $this->files->findShareByToken((string) $args['token']);
        if (!$share || ($share['expires_at'] !== null && $share['expires_at'] < gmdate('Y-m-d H:i:s'))) {
            return $this->json($response, ['ok' => false, 'errors' => ['Share link is invalid or expired.']], 404);
        }
        if ($share['permissions'] === 'view') {
            return $this->json($response, ['ok' => false, 'errors' => ['This share only allows viewing.']], 403);
        }
        $content = $this->storage->read($share['storage_path']);
        $this->audit->log(null, 'share.downloaded', 'share', (string) $share['id'], ['file' => $share['file_id']], 'share_recipient');
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', $share['mime_type'] ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($share['original_name']) . '"')
            ->withHeader('Content-Length', (string) strlen($content));
    }

    private function previewText(array $file): string
    {
        $content = $this->storage->read($file['storage_path']);
        if (str_starts_with($file['mime_type'], 'image/')) {
            $data = base64_encode($content);
            return '<img class="preview-image" src="data:' . $file['mime_type'] . ';base64,' . $data . '" alt="' . htmlspecialchars($file['name']) . '">';
        }
        if (str_starts_with($file['mime_type'], 'text/') || $file['mime_type'] === 'application/json') {
            return '<pre>' . htmlspecialchars(substr($content, 0, 20000)) . '</pre>';
        }
        return '<p>No inline preview available for this file type. <a href="/files/' . (int) $file['id'] . '/download">Download</a> instead.</p>';
    }

    private function renderShareView(Response $response, array $share): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Shared file</title><style>
            body{font-family:system-ui,sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem;color:#1b2a41}
            .card{border:1px solid #d6e2ef;border-radius:8px;padding:1.5rem}
            .btn{display:inline-block;margin-top:1rem;padding:.6rem 1rem;background:#2f6fed;color:#fff;border-radius:6px;text-decoration:none}
        </style></head><body>
        <div class="card">
          <h2>' . htmlspecialchars($share['file_name']) . '</h2>
          <p>Shared by <strong>' . htmlspecialchars($share['owner_id'] ? 'account#' . $share['owner_id'] : 'unknown') . '</strong> via a '
            . htmlspecialchars($share['scope']) . ' share link.</p>
          <p>Size: ' . number_format((int) $share['size_bytes']) . ' bytes · Type: ' . htmlspecialchars($share['mime_type']) . '</p>
          <p>Permissions: ' . htmlspecialchars($share['permissions']) . '</p>';
        if ($share['permissions'] !== 'view') {
            $html .= '<a class="btn" href="/s/' . htmlspecialchars($share['token']) . '/download">Download file</a>';
        }
        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderSharePassword(Response $response, array $share, string $error = ''): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Protected share</title><style>
            body{font-family:system-ui,sans-serif;max-width:480px;margin:3rem auto;padding:0 1rem;color:#1b2a41}
            input{width:100%;padding:.5rem;border:1px solid #cbd5e1;border-radius:6px}
            .btn{margin-top:1rem;padding:.6rem 1rem;background:#2f6fed;color:#fff;border:0;border-radius:6px;cursor:pointer}
            .err{color:#b91c1c;font-size:.9rem}
        </style></head><body>
        <form method="get" action="/s/' . htmlspecialchars($share['token']) . '">
          <h2>Password protected share</h2>
          <p>' . htmlspecialchars($share['file_name']) . ' is protected.</p>
          <label>Share password <input type="password" name="password" required></label>
          ' . ($error !== '' ? '<p class="err">' . htmlspecialchars($error) . '</p>' : '') . '
          <button class="btn" type="submit">Unlock</button>
        </form></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderShareError(Response $response, string $message): Response
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Share unavailable</title></head>
        <body style="font-family:system-ui;margin:3rem auto;max-width:480px;padding:0 1rem">
        <h2>Share unavailable</h2><p>' . htmlspecialchars($message) . '</p></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(404);
    }
}
