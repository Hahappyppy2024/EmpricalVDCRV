<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\FileManagerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FileController extends BaseController
{
    private const ROLES = ['customer'];

    private FileManagerService $service;

    public function __construct()
    {
        $this->service = new FileManagerService(\App\Support\Storage::path());
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $query = $request->getQueryParams();
        $siteId = isset($query['site']) ? (int) $query['site'] : null;
        $path = (string) ($query['path'] ?? '');

        [$error, $entries] = $this->service->browse($user, $siteId, $path);

        return $this->render($request, $response, 'files.php', [
            'pageTitle' => 'File manager',
            'activeNav' => 'files',
            'sites' => $this->service->listSites($user),
            'siteId' => $siteId,
            'path' => $path,
            'entries' => $entries,
            'browseError' => $error,
        ]);
    }

    public function upload(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $path = (string) ($body['path'] ?? '');
        $uploaded = $request->getUploadedFiles();
        $files = $uploaded['file'] ?? null;

        if ($files === null) {
            $this->flash($request, 'error', 'No file was selected for upload.');
        } else {
            if (!is_array($files)) {
                $files = [$files];
            }
            $failed = false;
            foreach ($files as $file) {
                $meta = [
                    'tmp_name' => $file->getFilePath() ?: $file->getStream()->getMetadata('uri'),
                    'name' => $file->getClientFilename(),
                    'size' => $file->getSize(),
                    'error' => $file->getError(),
                ];
                [$err] = $this->service->upload($user, $siteId, $path, $meta);
                if ($err !== null) {
                    $failed = true;
                    $this->flash($request, 'error', $err);
                    break;
                }
            }
            if (!$failed) {
                $this->flash($request, 'success', 'File(s) uploaded.');
            }
        }

        return $this->redirect($response, '/files?site=' . ($siteId ?? '') . '&path=' . urlencode($path));
    }

    public function newDir(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $path = (string) ($body['path'] ?? '');
        $dirName = (string) ($body['dir_name'] ?? '');
        $target = $path === '' ? $dirName : $path . '/' . $dirName;

        [$error] = $this->service->newDirectory($user, $siteId, $target);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Directory created.');

        return $this->redirect($response, '/files?site=' . ($siteId ?? '') . '&path=' . urlencode($path));
    }

    public function rename(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $path = (string) ($body['path'] ?? '');
        $oldPath = (string) ($body['old_path'] ?? '');
        $newName = (string) ($body['new_name'] ?? '');

        [$error] = $this->service->rename($user, $siteId, $oldPath, $newName);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Renamed successfully.');

        return $this->redirect($response, '/files?site=' . ($siteId ?? '') . '&path=' . urlencode($path));
    }

    public function delete(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $path = (string) ($body['path'] ?? '');
        $target = (string) ($body['target'] ?? '');

        [$error] = $this->service->delete($user, $siteId, $target);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Deleted.');

        return $this->redirect($response, '/files?site=' . ($siteId ?? '') . '&path=' . urlencode($path));
    }

    public function download(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $query = $request->getQueryParams();
        $siteId = isset($query['site']) ? (int) $query['site'] : null;
        $path = (string) ($query['path'] ?? '');

        [$error, $file] = $this->service->download($user, $siteId, $path);
        if ($error !== null) {
            return $this->error($response, $error, 404);
        }

        $stream = new \Slim\Psr7\Stream(fopen($file['path'], 'rb'));

        return $response
            ->withHeader('Content-Type', $file['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($file['name']) . '"')
            ->withHeader('Content-Length', (string) $file['size'])
            ->withBody($stream);
    }

    private function storageDir(): string
    {
        return \App\Support\Storage::path();
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $query = $request->getQueryParams();
        $siteId = isset($query['site']) ? (int) $query['site'] : null;
        $path = (string) ($query['path'] ?? '');

        [$error, $entries] = $this->service->browse($user, $siteId, $path);
        if ($error !== null) {
            return $this->error($response, $error, 404);
        }

        return $this->json($response, ['success' => true, 'path' => $path, 'records' => $entries]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $path = (string) ($body['path'] ?? '');

        $uploaded = $request->getUploadedFiles();
        $file = $uploaded['file'] ?? null;
        if ($file === null) {
            return $this->error($response, 'A file upload is required.', 422);
        }
        $meta = [
            'tmp_name' => $file->getFilePath() ?: $file->getStream()->getMetadata('uri'),
            'name' => $file->getClientFilename(),
            'size' => $file->getSize(),
            'error' => $file->getError(),
        ];

        [$error, $record] = $this->service->upload($user, $siteId, $path, $meta);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'File stored.',
            'record' => $record,
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $siteId = isset($body['site_id']) && $body['site_id'] !== '' ? (int) $body['site_id'] : null;
        $oldPath = (string) ($body['old_path'] ?? '');
        $newName = (string) ($body['new_name'] ?? '');

        [$error] = $this->service->rename($user, $siteId, $oldPath, $newName);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'message' => 'File renamed.']);
    }
}
