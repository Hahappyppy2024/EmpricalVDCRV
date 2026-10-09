<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReviewerAssignmentController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $pdo = Database::pdo();
        $submissions = $pdo->query('SELECT s.*, u.display_name AS author_name FROM paper_submissions s JOIN users u ON u.id = s.author_id ORDER BY s.id DESC')->fetchAll();
        $reviewers = $pdo->query("SELECT id, username, display_name, affiliation FROM users WHERE roles LIKE '%reviewer%' ORDER BY display_name")->fetchAll();
        $assignments = $pdo->query('SELECT a.*, s.title AS submission_title, r.display_name AS reviewer_name FROM reviewer_assignments a JOIN paper_submissions s ON s.id = a.submission_id JOIN users r ON r.id = a.reviewer_id ORDER BY a.id DESC')->fetchAll();
        $conflicts = $pdo->query('SELECT c.*, s.title AS submission_title, r.display_name AS reviewer_name FROM conflicts_of_interest c JOIN paper_submissions s ON s.id = c.submission_id JOIN users r ON r.id = c.reviewer_id ORDER BY c.id DESC')->fetchAll();
        $html = View::render('reviewer_assignment', ['submissions' => $submissions, 'reviewers' => $reviewers, 'assignments' => $assignments, 'conflicts' => $conflicts, 'errors' => [], 'success' => null], $config);
        return View::html(View::layout('Reviewer Assignment', $html, $config, $user));
    }

    public function assign(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $subId = (int)($body['submission_id'] ?? 0);
        $revId = (int)($body['reviewer_id'] ?? 0);
        $dueDate = trim($body['due_date'] ?? '');

        $errors = [];
        if ($subId <= 0) $errors[] = 'Submission required.';
        if ($revId <= 0) $errors[] = 'Reviewer required.';
        if ($dueDate === '') $errors[] = 'Due date required.';

        if ($errors) {
            $request = $request->withAttribute('errors', $errors);
            return $this->index($request, $response, $args);
        }

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM reviewer_assignments WHERE submission_id = ? AND reviewer_id = ?');
        $exists->execute([$subId, $revId]);
        if (!$exists->fetch()) {
            $ins = $pdo->prepare('INSERT INTO reviewer_assignments (submission_id, reviewer_id, assigned_by, status, due_date) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$subId, $revId, $user['id'], 'assigned', $dueDate]);
            $pdo->prepare('UPDATE paper_submissions SET status = ? WHERE id = ? AND status = ?')->execute(['under_review', $subId, 'submitted']);
            AuditService::log($user['id'], 'assign.reviewer', 'submission', (string)$subId, "Reviewer $revId assigned");
        } else {
            $errors[] = 'Assignment already exists.';
            $request = $request->withAttribute('errors', $errors);
            return $this->index($request, $response, $args);
        }

        View::flash('success', 'Reviewer assigned.');
        return View::redirect('/reviewer_assignment');
    }

    public function declareConflict(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $subId = (int)($body['submission_id'] ?? 0);
        $revId = (int)($body['reviewer_id'] ?? 0);
        $reason = trim($body['reason'] ?? '');
        if ($subId <= 0 || $revId <= 0 || $reason === '') {
            View::flash('error', 'Submission, reviewer, and reason are required.');
            return View::redirect('/reviewer_assignment');
        }
        $ins = Database::pdo()->prepare('INSERT OR IGNORE INTO conflicts_of_interest (submission_id, reviewer_id, declared_by, reason) VALUES (?, ?, ?, ?)');
        $ins->execute([$subId, $revId, $user['id'], $reason]);
        AuditService::log($user['id'], 'assign.coi', 'submission', (string)$subId, "COI with reviewer $revId");
        View::flash('success', 'Conflict declared.');
        return View::redirect('/reviewer_assignment');
    }

    public function removeAssignment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $aid = (int)($args['id'] ?? 0);
        if ($aid <= 0) return View::redirect('/reviewer_assignment');
        Database::pdo()->prepare('DELETE FROM reviewer_assignments WHERE id = ?')->execute([$aid]);
        AuditService::log($user['id'], 'assign.remove', 'assignment', (string)$aid, 'Removed assignment');
        View::flash('success', 'Assignment removed.');
        return View::redirect('/reviewer_assignment');
    }
}