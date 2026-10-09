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

class BulkExportsController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $pdo = Database::pdo();
        $jobs = $pdo->prepare('SELECT * FROM bulk_export_jobs WHERE chair_id = ? ORDER BY id DESC LIMIT 50');
        $jobs->execute([$user['id']]);
        $jobs = $jobs->fetchAll();
        $html = View::render('bulk_exports', ['jobs' => $jobs, 'errors' => [], 'success' => null], $config);
        return View::html(View::layout('Bulk Exports', $html, $config, $user));
    }

    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $format = trim($body['format'] ?? 'csv');
        $scope = trim($body['scope'] ?? 'submissions');
        if (!in_array($format, ['csv', 'tsv'], true)) $format = 'csv';
        if (!in_array($scope, ['submissions', 'reviews', 'decisions'], true)) $scope = 'submissions';

        $pdo = Database::pdo();
        $rows = [];
        $headers = [];
        if ($scope === 'submissions') {
            $headers = ['id', 'title', 'topic', 'status', 'author', 'created_at'];
            $rows = $pdo->query('SELECT s.id, s.title, s.topic, s.status, u.display_name AS author, s.created_at FROM paper_submissions s JOIN users u ON u.id = s.author_id ORDER BY s.id')->fetchAll();
        } elseif ($scope === 'reviews') {
            $headers = ['id', 'submission_id', 'submission_title', 'reviewer', 'score', 'confidence', 'recommendation', 'status'];
            $rows = $pdo->query('SELECT r.id, r.submission_id, s.title AS submission_title, u.display_name AS reviewer, r.score, r.confidence, r.recommendation, r.status FROM reviews r JOIN paper_submissions s ON s.id = r.submission_id JOIN users u ON u.id = r.reviewer_id ORDER BY r.id')->fetchAll();
        } else {
            $headers = ['id', 'submission_id', 'submission_title', 'decision', 'chair', 'summary', 'created_at'];
            $rows = $pdo->query('SELECT d.id, d.submission_id, s.title AS submission_title, d.decision, u.display_name AS chair, d.summary, d.created_at FROM decisions d JOIN paper_submissions s ON s.id = d.submission_id JOIN users u ON u.id = d.chair_id ORDER BY d.id')->fetchAll();
        }

        $sep = $format === 'tsv' ? "\t" : ',';
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers, ',');
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = str_replace([",", "\n", "\r"], [' ', ' ', ' '], (string)($row[$h] ?? ''));
            }
            if ($format === 'tsv') {
                fwrite($fh, implode("\t", $line) . "\n");
            } else {
                fputcsv($fh, $line, ',');
            }
        }
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        $exportDir = rtrim($config['paths']['storage'], '/') . '/exports';
        if (!is_dir($exportDir)) mkdir($exportDir, 0777, true);
        $fname = $scope . '_' . date('Ymd_His') . '.' . $format;
        $relPath = './storage/exports/' . $fname;
        file_put_contents($exportDir . '/' . $fname, $content);

        $ins = $pdo->prepare('INSERT INTO bulk_export_jobs (chair_id, format, scope, status, stored_path, record_count) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->execute([$user['id'], $format, $scope, 'completed', $relPath, count($rows)]);
        AuditService::log($user['id'], 'export.bulk', 'export', $fname, "$scope records: " . count($rows));
        View::flash('success', "Exported " . count($rows) . " rows.");
        return View::redirect('/bulk_exports');
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $jobId = (int)($args['id'] ?? 0);
        $stmt = Database::pdo()->prepare('SELECT * FROM bulk_export_jobs WHERE id = ? AND chair_id = ?');
        $stmt->execute([$jobId, $user['id']]);
        $job = $stmt->fetch();
        if (!$job) return View::json(['error' => 'not_found'], 404);
        $absolute = dirname(__DIR__, 2) . '/' . $job['stored_path'];
        if (!file_exists($absolute)) return View::json(['error' => 'missing'], 404);
        $fh = fopen($absolute, 'rb');
        $stream = new Stream($fh);
        $ctype = $job['format'] === 'tsv' ? 'text/tab-separated-values' : 'text/csv';
        return $response
            ->withHeader('Content-Type', $ctype)
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($job['stored_path']) . '"')
            ->withBody($stream);
    }
}