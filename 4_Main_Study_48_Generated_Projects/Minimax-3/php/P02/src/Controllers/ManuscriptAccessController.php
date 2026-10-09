<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

class ManuscriptAccessController
{
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $subId = (int)($args['submission_id'] ?? 0);
        $sub = $this->visibleSubmission($user, $subId);
        if (!$sub) {
            $body = View::render('error_page', ['code' => 404, 'message' => 'Submission not found or access denied.'], $config);
            return View::html(View::layout('Not Found', $body, $config, $user), 404);
        }
        $files = Database::pdo()->prepare('SELECT * FROM stored_files WHERE submission_id = ? ORDER BY id ASC');
        $files->execute([$subId]);
        $files = $files->fetchAll();
        $body = View::render('manuscript_access', ['submission' => $sub, 'files' => $files], $config);
        return View::html(View::layout('Manuscript Access', $body, $config, $user));
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $subId = (int)($args['submission_id'] ?? 0);
        $fileId = (int)($args['file_id'] ?? 0);

        $sub = $this->visibleSubmission($user, $subId);
        if (!$sub) {
            return View::json(['error' => 'not_found', 'message' => 'Submission not found.'], 404);
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE id = ? AND submission_id = ?');
        $stmt->execute([$fileId, $subId]);
        $file = $stmt->fetch();
        if (!$file) {
            return View::json(['error' => 'not_found', 'message' => 'File not found.'], 404);
        }

        $absolutePath = dirname(__DIR__, 2) . '/' . $file['stored_path'];
        if (!file_exists($absolutePath)) {
            return View::json(['error' => 'missing', 'message' => 'File missing on disk.'], 404);
        }

        $log = Database::pdo()->prepare('INSERT INTO manuscript_access_log (user_id, submission_id, file_id, action, ip_address) VALUES (?, ?, ?, ?, ?)');
        $log->execute([$user['id'], $subId, $fileId, 'download', $_SERVER['REMOTE_ADDR'] ?? '']);
        AuditService::log($user['id'], 'manuscript.download', 'submission', (string)$subId, 'file=' . $fileId);

        $fh = fopen($absolutePath, 'rb');
        $stream = new Stream($fh);
        return $response
            ->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
            ->withBody($stream);
    }

    private function visibleSubmission(array $user, int $subId): ?array
    {
        if ($subId <= 0) return null;
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT s.*, u.display_name AS author_name FROM paper_submissions s JOIN users u ON u.id = s.author_id WHERE s.id = ?');
        $stmt->execute([$subId]);
        $sub = $stmt->fetch();
        if (!$sub) return null;
        $isChair = in_array('chair', $user['roles'], true) || in_array('admin', $user['roles'], true);
        if ($isChair) return $sub;
        if ((int)$sub['author_id'] === (int)$user['id']) return $sub;
        if (in_array('reviewer', $user['roles'], true)) {
            $check = $pdo->prepare('SELECT id FROM reviewer_assignments WHERE submission_id = ? AND reviewer_id = ?');
            $check->execute([$subId, $user['id']]);
            if ($check->fetch()) return $sub;
        }
        return null;
    }
}