<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FileRepository;
use App\Repositories\SiteRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\UploadedFile;

final class FileManagerController
{
    public function __construct(
        private FileRepository $files,
        private SiteRepository $sites,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $siteId = $request->getQueryParams()['site_id'] ?? null;
        $items = $this->files->listForUser((int)$user['id'], $siteId ? (int)$siteId : null);
        $sites = $this->sites->listForUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'files', [
            'user' => $user, 'items' => $items, 'sites' => $sites,
            'currentSite' => $siteId ? (int)$siteId : null,
            'flash' => Flash::pull(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $file = $this->files->findOwned((int)$args['id'], (int)$user['id']);
        if (!$file) return View::json($response, ['error' => 'not_found'], 404);
        return View::json($response, ['file' => $file]);
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $dir = $request->getParsedBody()['parent_path'] ?? '/';
        $siteIdRaw = $request->getParsedBody()['site_id'] ?? null;
        $siteId = ($siteIdRaw === '' || $siteIdRaw === null) ? null : (int)$siteIdRaw;
        if ($siteId !== null) {
            $site = $this->sites->findOwned($siteId, (int)$user['id']);
            if (!$site) return View::json($response, ['error' => 'site_not_owned'], 403);
        }

        $files = $request->getUploadedFiles();
        $uploaded = $files['file'] ?? null;
        if (!$uploaded instanceof UploadedFile || $uploaded->getError() !== UPLOAD_ERR_OK) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'upload_failed'], 422);
            Flash::set('error', 'Upload failed'); return View::redirect($response, '/files?error=upload');
        }
        $size = (int)$uploaded->getSize();
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'invalid_size'], 422);
            Flash::set('error', 'File empty or too large (max 25MB)'); return View::redirect($response, '/files?error=size');
        }
        $clientName = $uploaded->getClientFilename() ?: 'upload.bin';
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $clientName);
        $path = rtrim($dir, '/') . '/' . $name;
        if ($this->files->existsAt((int)$user['id'], $path)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'duplicate'], 409);
            Flash::set('error', 'File already exists'); return View::redirect($response, '/files?error=duplicate');
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/uploads/' . (int)$user['id'];
        if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
        $target = $storageDir . '/' . bin2hex(random_bytes(8)) . '_' . $name;
        $uploaded->moveTo($target);

        $id = $this->files->create((int)$user['id'], $siteId, rtrim($dir, '/') ?: '/', $name, $path, $size, $uploaded->getClientMediaType() ?? 'application/octet-stream');
        AuditLogger::log((int)$user['id'], $user['role'], 'file.upload', 'file', $id, "$name ($size bytes)", $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Uploaded $name");
        return View::redirect($response, '/files');
    }

    public function rename(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $file = $this->files->findOwned((int)$args['id'], (int)$user['id']);
        if (!$file) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $newName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($body['new_name'] ?? ''));
        if ($newName === '' || $newName === null) return View::json($response, ['error' => 'invalid_name'], 422);
        $parent = dirname($file['path']);
        $parent = $parent === '.' ? '/' : $parent;
        $newPath = rtrim($parent, '/') . '/' . $newName;
        if ($this->files->existsAt((int)$user['id'], $newPath)) return View::json($response, ['error' => 'duplicate'], 409);
        $this->files->rename((int)$file['id'], (int)$user['id'], $newName, $newPath);
        AuditLogger::log((int)$user['id'], $user['role'], 'file.rename', 'file', (int)$file['id'], $newName, $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $file = $this->files->findOwned((int)$args['id'], (int)$user['id']);
        if (!$file) return View::json($response, ['error' => 'not_found'], 404);
        $this->files->delete((int)$file['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'file.delete', 'file', (int)$file['id'], $file['name'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'File removed');
        return View::redirect($response, '/files');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}