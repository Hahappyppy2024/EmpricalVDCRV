<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\ReviewRepository;
use Shop\Models\ProductRepository;
use Shop\Models\AuditRepository;
use Shop\Support\Validator;

final class ReviewsController extends BaseController
{
    public function apiIndex(Request $request, Response $response): Response
    {
        $pid = (int)($request->getQueryParams()['product_id'] ?? 0);
        if ($pid <= 0) {
            return $this->json($response, ['error' => 'product_id_required'], 400);
        }
        $includePending = SessionManager::has('moderator');
        return $this->json($response, ['reviews' => ReviewRepository::forProduct($pid, !$includePending)]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $data = $this->jsonBody($request);
        $pid = (int)($data['product_id'] ?? 0);
        $rating = (int)($data['rating'] ?? 0);
        $body = trim((string)($data['body'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $product = ProductRepository::find($pid);
        if (!$product || $rating < 1 || $rating > 5 || !Validator::text($body, 4, 2000)) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        $review = ReviewRepository::create($pid, (int)$user['id'], $rating, $title, $body);
        if (!empty($review['duplicate'])) {
            return $this->json($response, ['error' => 'duplicate_review'], 409);
        }
        AuditRepository::log((int)$user['id'], 'review_create', 'review', (string)($review['id'] ?? ''));
        return $this->json($response, ['review' => $review], 201);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        if (!SessionManager::has('moderator')) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        $id = (int)($args['id'] ?? 0);
        $data = $this->jsonBody($request);
        $status = (string)($data['status'] ?? '');
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return $this->json($response, ['error' => 'invalid_status'], 400);
        }
        ReviewRepository::setStatus($id, $status);
        AuditRepository::log((int)$user['id'], 'review_status', 'review', (string)$id, ['status' => $status]);
        return $this->json($response, ['review' => ReviewRepository::find($id)]);
    }

    public function moderationPage(Request $request, Response $response): Response
    {
        $this->requireRole($request, 'moderator');
        return $this->render($response, 'reviews_moderation.php', [
            'page_title' => 'Review moderation',
            'pending' => ReviewRepository::pending(),
        ]);
    }

    public function moderate(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'moderator');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/reviews/moderation');
        }
        $id = (int)($args['id'] ?? 0);
        $status = (string)$this->input($request, 'status');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            SessionManager::flash('error', 'Invalid status.');
            return $this->redirect($response, '/reviews/moderation');
        }
        ReviewRepository::setStatus($id, $status);
        AuditRepository::log((int)$user['id'], 'review_status', 'review', (string)$id, ['status' => $status]);
        SessionManager::flash('success', 'Review updated.');
        return $this->redirect($response, '/reviews/moderation');
    }
}