<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FrontendApiController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $user = SessionService::user();
        $pdo = Database::pdo();
        $errors = $pdo->query('SELECT e.*, u.username FROM frontend_error_logs e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 50')->fetchAll();
        $body = View::render('frontend_api_errors', ['errors' => $errors], $config);
        return View::html(View::layout('Frontend API Errors', $body, $config, $user));
    }

    public function reportError(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();
        $code = trim($body['code'] ?? '');
        $severity = trim($body['severity'] ?? 'info');
        $source = trim($body['source'] ?? 'unknown');
        $message = trim($body['message'] ?? '');
        $details = trim($body['details'] ?? '');
        if ($code === '' || $message === '') {
            return View::json(['ok' => false, 'error' => 'code and message required'], 400);
        }
        if (!in_array($severity, ['info', 'warning', 'error', 'critical'], true)) {
            $severity = 'info';
        }
        $stmt = Database::pdo()->prepare('INSERT INTO frontend_error_logs (user_id, code, severity, source, message, details) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([SessionService::userId(), $code, $severity, $source, $message, $details]);
        return View::json(['ok' => true, 'id' => (int)Database::pdo()->lastInsertId()]);
    }

    public function apiError(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $code = $args['code'] ?? '500';
        $allowed = ['400' => 'Bad Request', '401' => 'Unauthorized', '403' => 'Forbidden', '404' => 'Not Found', '409' => 'Conflict', '422' => 'Unprocessable Entity', '500' => 'Server Error'];
        $message = $allowed[$code] ?? 'API Error';
        return View::json(['ok' => false, 'error' => $message, 'code' => (int)$code], (int)$code);
    }
}