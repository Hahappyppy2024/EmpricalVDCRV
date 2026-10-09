<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SubmissionDiscoveryController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $params = $request->getQueryParams();
        $q = trim($params['q'] ?? '');
        $status = trim($params['status'] ?? '');

        $pdo = Database::pdo();
        $sql = 'SELECT s.*, u.display_name AS author_name FROM paper_submissions s JOIN users u ON u.id = s.author_id WHERE 1=1';
        $args2 = [];
        if ($q !== '') {
            $sql .= ' AND (s.title LIKE ? OR s.abstract LIKE ? OR s.keywords LIKE ?)';
            $args2[] = "%$q%";
            $args2[] = "%$q%";
            $args2[] = "%$q%";
        }
        if ($status !== '' && in_array($status, ['submitted', 'under_review', 'accepted', 'rejected', 'revisions'], true)) {
            $sql .= ' AND s.status = ?';
            $args2[] = $status;
        }
        $isChair = in_array('chair', $user['roles'], true) || in_array('admin', $user['roles'], true);
        if (!$isChair) {
            $sql .= ' AND s.author_id = ?';
            $args2[] = $user['id'];
        }
        $sql .= ' ORDER BY s.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args2);
        $results = $stmt->fetchAll();

        $html = View::render('submission_discovery', ['results' => $results, 'q' => $q, 'status' => $status], $config);
        return View::html(View::layout('Submission Discovery', $html, $config, $user));
    }
}