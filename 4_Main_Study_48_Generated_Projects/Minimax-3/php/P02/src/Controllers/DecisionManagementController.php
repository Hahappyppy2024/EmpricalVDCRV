<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DecisionManagementController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT s.id, s.title, s.status, u.display_name AS author_name, d.decision, d.summary, d.notification, d.created_at AS decided_at FROM paper_submissions s JOIN users u ON u.id = s.author_id LEFT JOIN decisions d ON d.submission_id = s.id ORDER BY s.id DESC')->fetchAll();
        $audits = AuditService::list(50);
        $html = View::render('decision_management', ['rows' => $rows, 'audits' => $audits, 'errors' => [], 'success' => null], $config);
        return View::html(View::layout('Decision Management', $html, $config, $user));
    }

    public function record(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $subId = (int)($body['submission_id'] ?? 0);
        $decision = trim($body['decision'] ?? '');
        $summary = trim($body['summary'] ?? '');
        $notification = trim($body['notification'] ?? '');
        $errors = [];
        if ($subId <= 0) $errors[] = 'Submission required.';
        if (!in_array($decision, ['accept', 'reject', 'revisions'], true)) $errors[] = 'Invalid decision.';
        if (strlen($summary) < 10) $errors[] = 'Summary must be at least 10 characters.';
        if ($errors) {
            $request = $request->withAttribute('errors', $errors);
            return $this->index($request, $response, $args);
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM decisions WHERE submission_id = ?');
        $exists->execute([$subId]);
        if ($exists->fetch()) {
            $upd = $pdo->prepare('UPDATE decisions SET chair_id = ?, decision = ?, summary = ?, notification = ? WHERE submission_id = ?');
            $upd->execute([$user['id'], $decision, $summary, $notification, $subId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO decisions (submission_id, chair_id, decision, summary, notification) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$subId, $user['id'], $decision, $summary, $notification]);
        }
        $status = $decision === 'accept' ? 'accepted' : ($decision === 'reject' ? 'rejected' : 'revisions');
        $pdo->prepare('UPDATE paper_submissions SET status = ? WHERE id = ?')->execute([$status, $subId]);

        AuditService::log($user['id'], 'decision.record', 'submission', (string)$subId, "Decision: $decision");
        View::flash('success', 'Decision recorded.');
        return View::redirect('/decision_management');
    }
}