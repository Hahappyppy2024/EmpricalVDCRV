<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DoubleBlindViewsController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $subId = (int)($args['submission_id'] ?? 0);
        $pdo = Database::pdo();
        $sub = $pdo->prepare('SELECT * FROM paper_submissions WHERE id = ?');
        $sub->execute([$subId]);
        $sub = $sub->fetch();
        if (!$sub) {
            $body = View::render('error_page', ['code' => 404, 'message' => 'Submission not found.'], $config);
            return View::html(View::layout('Not Found', $body, $config, $user), 404);
        }

        $isAuthor = (int)$sub['author_id'] === (int)$user['id'];
        $isChair = in_array('chair', $user['roles'], true) || in_array('admin', $user['roles'], true);
        $isReviewer = in_array('reviewer', $user['roles'], true);

        $pref = $pdo->prepare('SELECT view_mode FROM double_blind_preferences WHERE submission_id = ? AND user_id = ?');
        $pref->execute([$subId, $user['id']]);
        $pref = $pref->fetch();
        $defaultMode = 'blind';
        if ($isChair) $defaultMode = 'unblinded';
        if ($isAuthor) $defaultMode = 'blind';
        $mode = $pref['view_mode'] ?? ($request->getQueryParams()['mode'] ?? $defaultMode);

        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $newMode = trim($body['view_mode'] ?? '');
            if (in_array($newMode, ['blind', 'unblinded'], true)) {
                $up = $pdo->prepare('INSERT INTO double_blind_preferences (submission_id, user_id, view_mode) VALUES (?, ?, ?) ON CONFLICT(submission_id, user_id) DO UPDATE SET view_mode = excluded.view_mode');
                $up->execute([$subId, $user['id'], $newMode]);
                $mode = $newMode;
            }
            return View::redirect('/double_blind_views/' . $subId . '?mode=' . $mode);
        }

        $author = $pdo->prepare('SELECT username, display_name, affiliation FROM users WHERE id = ?');
        $author->execute([$sub['author_id']]);
        $author = $author->fetch();

        $showIdentity = ($mode === 'unblinded');

        $body = View::render('double_blind_views', [
            'submission' => $sub,
            'author' => $author,
            'showIdentity' => $showIdentity,
            'isChair' => $isChair,
            'isAuthor' => $isAuthor,
            'isReviewer' => $isReviewer,
        ], $config);
        return View::html(View::layout('Double-Blind View', $body, $config, $user));
    }
}