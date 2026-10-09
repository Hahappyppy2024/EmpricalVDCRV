<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RebuttalController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $pdo = Database::pdo();
        $subs = [];
        if (in_array('author', $user['roles'], true)) {
            $stmt = $pdo->prepare('SELECT id, title FROM paper_submissions WHERE author_id = ? ORDER BY id DESC');
            $stmt->execute([$user['id']]);
            $subs = $stmt->fetchAll();
        }
        $rebuttals = $pdo->query('SELECT r.*, s.title AS submission_title, u.display_name AS author_name FROM rebuttals r JOIN paper_submissions s ON s.id = r.submission_id JOIN users u ON u.id = r.author_id ORDER BY r.id DESC')->fetchAll();
        $html = View::render('rebuttal', ['user' => $user, 'mySubmissions' => $subs, 'rebuttals' => $rebuttals, 'errors' => [], 'success' => null], $config);
        return View::html(View::layout('Rebuttal', $html, $config, $user));
    }

    public function submit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['author']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $subId = (int)($body['submission_id'] ?? 0);
        $text = trim($body['body'] ?? '');
        $errors = [];
        if ($subId <= 0) $errors[] = 'Submission required.';
        if (strlen($text) < 30) $errors[] = 'Rebuttal must be at least 30 characters.';

        $pdo = Database::pdo();
        $own = $pdo->prepare('SELECT id FROM paper_submissions WHERE id = ? AND author_id = ?');
        $own->execute([$subId, $user['id']]);
        if (!$own->fetch()) $errors[] = 'You can only rebut your own submissions.';

        if ($errors) {
            $request = $request->withAttribute('errors', $errors);
            return $this->index($request, $response, $args);
        }

        $ins = $pdo->prepare('INSERT INTO rebuttals (submission_id, author_id, body) VALUES (?, ?, ?)');
        $ins->execute([$subId, $user['id'], $text]);
        AuditService::log($user['id'], 'rebuttal.submit', 'submission', (string)$subId, 'Author rebuttal submitted');
        View::flash('success', 'Rebuttal recorded.');
        return View::redirect('/rebuttal');
    }
}