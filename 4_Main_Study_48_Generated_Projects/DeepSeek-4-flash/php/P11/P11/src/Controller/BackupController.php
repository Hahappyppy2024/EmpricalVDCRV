<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BackupController extends BaseController
{
    private const ROLES = ['customer', 'admin'];

    private BackupService $service;

    public function __construct()
    {
        $this->service = new BackupService(\App\Support\Storage::path());
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'backups.php', [
            'pageTitle' => 'Backups',
            'activeNav' => 'backups',
            'section' => 'list',
            'backups' => $this->service->listForActor($user),
            'sites' => $user['role'] === 'admin' ? [] : $this->service->sitesFor($user),
        ]);
    }

    public function store(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $name = (string) ($body['name'] ?? '');
        $sourceType = (string) ($body['source_type'] ?? 'full');
        $sourceId = isset($body['source_id']) && $body['source_id'] !== '' ? (int) $body['source_id'] : null;

        [$error, $id] = $this->service->create($user, $name, $sourceType, $sourceId);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Backup created.');
        if ($error === null) {
            return $this->redirect($response, '/backups');
        }

        return $this->redirect($response, '/backups');
    }

    public function upload(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $uploaded = $request->getUploadedFiles();
        $file = $uploaded['file'] ?? null;

        if ($file === null) {
            $this->flash($request, 'error', 'No backup file was selected.');
        } else {
            $meta = [
                'tmp_name' => $file->getFilePath() ?: $file->getStream()->getMetadata('uri'),
                'name' => $file->getClientFilename(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
            ];
            [$error, $id] = $this->service->upload($user, $meta);
            $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Backup uploaded.');
        }

        return $this->redirect($response, '/backups');
    }

    public function download(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        [$error, $file] = $this->service->download($user, (int) $args['id']);
        if ($error !== null) {
            return $this->error($response, $error, 404);
        }

        $stream = new \Slim\Psr7\Stream(fopen($file['path'], 'rb'));

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($file['name']) . '"')
            ->withHeader('Content-Length', (string) $file['size'])
            ->withBody($stream);
    }

    public function restore(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->restoreForActor($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Backup restored.');

        return $this->redirect($response, '/backups');
    }

    public function delete(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $error = $this->service->deleteForActor($user, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Backup deleted.');

        return $this->redirect($response, '/backups');
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

        return $this->json($response, ['success' => true, 'records' => $this->service->listForActor($user)]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->create(
            $user,
            (string) ($body['name'] ?? ''),
            (string) ($body['source_type'] ?? 'full'),
            isset($body['source_id']) && $body['source_id'] !== '' ? (int) $body['source_id'] : null
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Backup created.',
            'record' => $this->service->getForActor($user, $id),
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $error = $this->service->restoreForActor($user, (int) $args['id']);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'message' => 'Backup restore started.']);
    }
}
