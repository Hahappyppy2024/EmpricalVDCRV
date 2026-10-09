<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConferencePhasesController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $phases = Database::pdo()->query('SELECT * FROM conference_phases ORDER BY id ASC')->fetchAll();
        $body = View::render('conference_phases', ['phases' => $phases, 'errors' => [], 'success' => null], $config);
        return View::html(View::layout('Conference Phases', $body, $config, $user));
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireRole(['chair', 'admin']);
        $user = SessionService::user();
        $body = $request->getParsedBody();
        $id = (int)($args['id'] ?? 0);
        $startDate = trim($body['start_date'] ?? '');
        $endDate = trim($body['end_date'] ?? '');
        $status = trim($body['status'] ?? 'scheduled');
        $errors = [];
        if ($startDate === '' || $endDate === '') $errors[] = 'Start and end dates are required.';
        if (strtotime($endDate) < strtotime($startDate)) $errors[] = 'End date must be after start date.';
        if (!in_array($status, ['open', 'scheduled', 'closed'], true)) $errors[] = 'Invalid status.';
        $phases = Database::pdo()->query('SELECT * FROM conference_phases ORDER BY id ASC')->fetchAll();
        if ($errors) {
            $html = View::render('conference_phases', ['phases' => $phases, 'errors' => $errors, 'success' => null], $config);
            return View::html(View::layout('Conference Phases', $html, $config, $user));
        }
        $stmt = Database::pdo()->prepare('UPDATE conference_phases SET start_date = ?, end_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$startDate, $endDate, $status, $id]);
        AuditService::log(SessionService::userId(), 'phase.update', 'phase', (string)$id, "Phase updated to $status ($startDate - $endDate)");
        View::flash('success', 'Phase updated.');
        $phases = Database::pdo()->query('SELECT * FROM conference_phases ORDER BY id ASC')->fetchAll();
        $html = View::render('conference_phases', ['phases' => $phases, 'errors' => [], 'success' => 'Phase updated.'], $config);
        return View::html(View::layout('Conference Phases', $html, $config, $user));
    }

    public function apiIndex(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $phases = Database::pdo()->query('SELECT * FROM conference_phases ORDER BY id ASC')->fetchAll();
        return View::json(['phases' => $phases]);
    }
}