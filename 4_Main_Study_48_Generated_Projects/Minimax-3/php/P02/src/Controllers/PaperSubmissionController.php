<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PaperSubmissionController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['author']);
        $user = SessionService::user();
        $errors = $request->getAttribute('errors') ?? [];
        $success = $request->getAttribute('success');
        $phase = Database::pdo()->query("SELECT status FROM conference_phases WHERE phase_key = 'submission'")->fetch();
        $phaseOpen = ($phase && $phase['status'] === 'open');
        $mySubs = Database::pdo()->prepare('SELECT * FROM paper_submissions WHERE author_id = ? ORDER BY id DESC');
        $mySubs->execute([$user['id']]);
        $mySubs = $mySubs->fetchAll();
        $html = View::render('paper_submission', ['errors' => $errors, 'success' => $success, 'phaseOpen' => $phaseOpen, 'mySubs' => $mySubs], $config);
        return View::html(View::layout('Paper Submission', $html, $config, $user));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['author']);
        $user = SessionService::user();

        $uploadDir = rtrim($config['paths']['storage'], '/') . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $body = $request->getParsedBody();
        $title = trim($body['title'] ?? '');
        $abstract = trim($body['abstract'] ?? '');
        $keywords = trim($body['keywords'] ?? '');
        $topic = trim($body['topic'] ?? '');

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($abstract === '' || strlen($abstract) < 30) $errors[] = 'Abstract must be at least 30 characters.';
        if ($keywords === '') $errors[] = 'Keywords are required.';

        $files = $request->getUploadedFiles();
        $pdf = $files['pdf'] ?? null;
        $storedName = '';
        $storedPath = '';
        $originalName = '';
        if (!$pdf || $pdf->getError() !== UPLOAD_ERR_OK) {
            $errors[] = 'PDF upload required.';
        } else {
            $mime = $pdf->getClientMediaType() ?? '';
            $size = $pdf->getSize() ?? 0;
            $originalName = $pdf->getClientFilename() ?? 'manuscript.pdf';
            if ($mime !== 'application/pdf' && !str_ends_with(strtolower($originalName), '.pdf')) {
                $errors[] = 'File must be a PDF.';
            } elseif ($size > 5 * 1024 * 1024) {
                $errors[] = 'File too large (max 5MB).';
            } else {
                $storedName = 'paper_' . $user['id'] . '_' . time() . '.pdf';
                $storedPath = './storage/uploads/' . $storedName;
                $pdf->moveTo($uploadDir . '/' . $storedName);
            }
        }

        if ($errors) {
            $request = $request->withAttribute('errors', $errors);
            return $this->index($request, $response, $args);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO paper_submissions (author_id, title, abstract, keywords, topic, pdf_path, status, submission_phase) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $title, $abstract, $keywords, $topic, $storedPath, 'submitted', 'submission']);
        $subId = (int)$pdo->lastInsertId();

        $fstmt = $pdo->prepare('INSERT INTO stored_files (submission_id, owner_id, original_name, stored_path, mime_type, size_bytes, kind) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $fstmt->execute([$subId, $user['id'], $originalName, $storedPath, 'application/pdf', filesize($uploadDir . '/' . $storedName), 'manuscript']);

        AuditService::log($user['id'], 'paper.submit', 'submission', (string)$subId, "Title: $title");
        View::flash('success', 'Submission received.');
        return View::redirect('/paper_submission');
    }
}