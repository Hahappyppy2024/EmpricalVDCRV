<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReviewingController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['reviewer']);
        $user = SessionService::user();
        $pdo = Database::pdo();
        $list = $pdo->prepare('SELECT a.*, s.title AS submission_title, s.id AS submission_id, r.score, r.id AS review_id FROM reviewer_assignments a JOIN paper_submissions s ON s.id = a.submission_id LEFT JOIN reviews r ON r.assignment_id = a.id WHERE a.reviewer_id = ? ORDER BY a.due_date ASC');
        $list->execute([$user['id']]);
        $list = $list->fetchAll();
        $html = View::render('reviewing_list', ['assignments' => $list], $config);
        return View::html(View::layout('Reviewing', $html, $config, $user));
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['reviewer']);
        $user = SessionService::user();
        $aid = (int)($args['assignment_id'] ?? 0);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT a.*, s.id AS submission_id, s.title AS submission_title, s.abstract, s.keywords, s.topic FROM reviewer_assignments a JOIN paper_submissions s ON s.id = a.submission_id WHERE a.id = ? AND a.reviewer_id = ?');
        $stmt->execute([$aid, $user['id']]);
        $assignment = $stmt->fetch();
        if (!$assignment) {
            $body = View::render('error_page', ['code' => 404, 'message' => 'Assignment not found or not yours.'], $config);
            return View::html(View::layout('Not Found', $body, $config, $user), 404);
        }
        $rev = $pdo->prepare('SELECT * FROM reviews WHERE assignment_id = ?');
        $rev->execute([$aid]);
        $rev = $rev->fetch();
        $errors = $request->getAttribute('errors') ?? [];
        $success = $request->getAttribute('success');
        $html = View::render('reviewing_form', ['assignment' => $assignment, 'review' => $rev, 'errors' => $errors, 'success' => $success], $config);
        return View::html(View::layout('Submit Review', $html, $config, $user));
    }

    public function submit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['reviewer']);
        $user = SessionService::user();
        $aid = (int)($args['assignment_id'] ?? 0);
        $body = $request->getParsedBody();
        $score = (int)($body['score'] ?? 0);
        $confidence = (int)($body['confidence'] ?? 0);
        $commentsToAuthor = trim($body['comments_to_author'] ?? '');
        $commentsToChair = trim($body['comments_to_chair'] ?? '');
        $recommendation = trim($body['recommendation'] ?? '');

        $errors = [];
        if ($score < 1 || $score > 7) $errors[] = 'Score must be 1-7.';
        if ($confidence < 1 || $confidence > 5) $errors[] = 'Confidence must be 1-5.';
        if (strlen($commentsToAuthor) < 20) $errors[] = 'Comments to author must be at least 20 characters.';
        if (!in_array($recommendation, ['accept', 'weak_accept', 'borderline', 'weak_reject', 'reject'], true)) $errors[] = 'Invalid recommendation.';

        $pdo = Database::pdo();
        $a = $pdo->prepare('SELECT * FROM reviewer_assignments WHERE id = ? AND reviewer_id = ?');
        $a->execute([$aid, $user['id']]);
        $a = $a->fetch();
        if (!$a) return View::redirect('/reviewing');

        if ($errors) {
            $request = $request->withAttribute('errors', $errors);
            return $this->show($request, $response, $args);
        }

        $exists = $pdo->prepare('SELECT id FROM reviews WHERE assignment_id = ?');
        $exists->execute([$aid]);
        $exists = $exists->fetch();
        if ($exists) {
            $upd = $pdo->prepare('UPDATE reviews SET score = ?, confidence = ?, comments_to_author = ?, comments_to_chair = ?, recommendation = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE assignment_id = ?');
            $upd->execute([$score, $confidence, $commentsToAuthor, $commentsToChair, $recommendation, 'submitted', $aid]);
        } else {
            $ins = $pdo->prepare('INSERT INTO reviews (assignment_id, reviewer_id, submission_id, score, confidence, comments_to_author, comments_to_chair, recommendation, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$aid, $user['id'], $a['submission_id'], $score, $confidence, $commentsToAuthor, $commentsToChair, $recommendation, 'submitted']);
        }
        $pdo->prepare('UPDATE reviewer_assignments SET status = ? WHERE id = ?')->execute(['completed', $aid]);

        AuditService::log($user['id'], 'review.submit', 'submission', (string)$a['submission_id'], "Score $score / Rec $recommendation");
        View::flash('success', 'Review submitted.');
        return View::redirect('/reviewing');
    }
}