<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\FileRepository;
use App\Repository\SiteRepository;

final class FileManagerService
{
    private FileRepository $files;

    private SiteRepository $sites;

    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->files = new FileRepository();
        $this->sites = new SiteRepository();
        $this->storageDir = rtrim($storageDir ?? \App\Support\Storage::path(), '/\\');
    }

    public function listSites(array $user): array
    {
        return $this->sites->allForUser((int) $user['id']);
    }

    /**
     * @return array{0: ?string, 1: array} [error, entries]
     */
    public function browse(array $user, ?int $siteId, string $path): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.', []];
        }

        $base = $site !== null ? $site['name'] : '';
        $rel = $this->cleanRel($base, $path);
        $dir = $this->targetPath((int) $user['id'], $rel);
        if (!is_dir($dir)) {
            return ['Directory does not exist.', []];
        }

        $entries = [];
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . '/' . $item;
            $isDir = is_dir($full);
            $size = $isDir ? 0 : filesize($full);
            $mime = $isDir ? 'inode/directory' : (mime_content_type($full) ?: 'application/octet-stream');
            $entries[] = [
                'name' => $item,
                'path' => ltrim($rel === '' ? $item : $rel . '/' . $item, '/'),
                'is_dir' => $isDir ? 1 : 0,
                'size' => $size,
                'mime_type' => $mime,
                'updated_at' => date('Y-m-d H:i:s', filemtime($full)),
            ];
        }
        usort($entries, fn ($a, $b) => $b['is_dir'] <=> $a['is_dir'] ?: strcasecmp($a['name'], $b['name']));

        return [null, $entries];
    }

    /**
     * @return array{0: ?string, 1: ?array} [error, file record]
     */
    public function upload(array $user, ?int $siteId, string $path, array $uploaded): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.', null];
        }

        if (!isset($uploaded['tmp_name']) || $uploaded['error'] !== UPLOAD_ERR_OK) {
            return ['Upload failed or file is missing.', null];
        }
        $base = $site !== null ? $site['name'] : '';
        $dir = $this->targetPath((int) $user['id'], $this->cleanRel($base, $path));
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return ['Target directory could not be created.', null];
            }
        }

        $fileName = basename((string) $uploaded['name']);
        $fileSize = (int) $uploaded['size'];
        if ($fileSize > 8 * 1024 * 1024) {
            return ['File exceeds the 8 MB upload limit.', null];
        }
        if ($fileName === '') {
            return ['Invalid file name.', null];
        }
        $mime = mime_content_type($uploaded['tmp_name']) ?: 'application/octet-stream';

        $relPath = ltrim($this->cleanRel($base, $path) === '' ? $fileName : $this->cleanRel($base, $path) . '/' . $fileName, '/');
        $target = $dir . '/' . $fileName;
        if (!move_uploaded_file($uploaded['tmp_name'], $target)) {
            return ['Could not store the uploaded file.', null];
        }

        $existing = $this->files->findByPath((int) $user['id'], $relPath);
        if ($existing !== null) {
            $this->files->updateMeta((int) $existing['id'], null, null, $fileSize, $mime);
            $meta = $this->files->findByPath((int) $user['id'], $relPath);
        } else {
            $this->files->createMeta((int) $user['id'], $siteId, $relPath, $fileName, $fileSize, $mime, 0);
            $meta = $this->files->findByPath((int) $user['id'], $relPath);
        }

        return [null, $meta];
    }

    /**
     * @return array{0: ?string} [error]
     */
    public function newDirectory(array $user, ?int $siteId, string $path): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.'];
        }
        $path = trim($path);
        if ($path === '') {
            return ['Directory name is required.'];
        }
        $base = $site !== null ? $site['name'] : '';
        $dir = $this->targetPath((int) $user['id'], $this->cleanRel($base, $path));
        if (is_dir($dir)) {
            return ['Directory already exists.'];
        }
        if (!mkdir($dir, 0777, true)) {
            return ['Could not create directory.'];
        }
        $relPath = ltrim($this->cleanRel($base, $path), '/');
        $this->files->createMeta((int) $user['id'], $siteId, $relPath, basename($relPath), 0, 'inode/directory', 1);

        return [null];
    }

    /**
     * @return array{0: ?string} [error]
     */
    public function rename(array $user, ?int $siteId, string $oldPath, string $newName): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.'];
        }
        $newName = trim($newName);
        if ($oldPath === '' || $newName === '') {
            return ['Source path and new name are required.'];
        }
        $base = $site !== null ? $site['name'] : '';
        $oldRel = $this->cleanRel($base, $oldPath);
        $src = $this->targetPath((int) $user['id'], $oldRel);
        if (!file_exists($src)) {
            return ['File or directory does not exist.'];
        }
        $parent = dirname($src);
        $dst = $parent . '/' . basename($newName);
        if (file_exists($dst)) {
            return ['A file or directory with that name already exists.'];
        }
        if (!rename($src, $dst)) {
            return ['Could not rename the file or directory.'];
        }

        $newRel = ltrim($this->cleanRel($base, $oldPath) === '' ? $newName : dirname($this->cleanRel($base, $oldPath)) . '/' . $newName, '/');
        $oldPrefix = ltrim($oldRel, '/');
        if (is_dir($dst)) {
            $this->files->updatePathsForRename((int) $user['id'], $oldPrefix, ltrim($newRel, '/'));
        }
        $meta = $this->files->findByPath((int) $user['id'], $oldPrefix);
        if ($meta !== null) {
            $this->files->updateMeta((int) $meta['id'], $newRel, basename($newRel));
        }

        return [null];
    }

    /**
     * @return array{0: ?string} [error]
     */
    public function delete(array $user, ?int $siteId, string $path): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.'];
        }
        $base = $site !== null ? $site['name'] : '';
        $rel = $this->cleanRel($base, $path);
        $target = $this->targetPath((int) $user['id'], $rel);
        if (!file_exists($target)) {
            return ['File or directory does not exist.'];
        }
        if (is_dir($target)) {
            $this->removeDir($target);
        } else {
            if (!unlink($target)) {
                return ['Could not delete the file.'];
            }
        }
        $this->files->deleteMetaForPathPrefix((int) $user['id'], ltrim($rel, '/'));

        return [null];
    }

    /**
     * @return array{0: ?string, 1: ?array} [error, [path, mime, name, size]]
     */
    public function download(array $user, ?int $siteId, string $path): array
    {
        $site = $siteId !== null ? $this->sites->findForUser($siteId, (int) $user['id']) : null;
        if ($siteId !== null && $site === null) {
            return ['Unknown or out-of-scope site.', null];
        }
        $base = $site !== null ? $site['name'] : '';
        $rel = $this->cleanRel($base, $path);
        $target = $this->targetPath((int) $user['id'], $rel);
        if (!is_file($target)) {
            return ['File does not exist.', null];
        }
        $mime = mime_content_type($target) ?: 'application/octet-stream';

        return [null, ['path' => $target, 'mime' => $mime, 'name' => basename($target), 'size' => filesize($target)]];
    }

    public function baseDir(int $userId): string
    {
        $dir = $this->storageDir . '/files/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function targetPath(int $userId, string $rel): string
    {
        return $this->baseDir($userId) . ($rel === '' ? '' : '/' . $rel);
    }

    private function cleanRel(string $base, string $path): string
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = ltrim($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = $part;
        }
        $rel = implode('/', $parts);
        if ($base !== '') {
            $rel = $rel === '' ? $base : $base . '/' . $rel;
        }

        return $rel;
    }

    private function removeDir(string $dir): void
    {
        $items = glob($dir . '/*');
        if ($items !== false) {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    $this->removeDir($item);
                } else {
                    unlink($item);
                }
            }
        }
        rmdir($dir);
    }
}
