<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\OrderRepository;
use Shop\Models\AuditRepository;

final class OrderLifecycleController extends BaseController
{
    public function page(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$order) {
            return $this->render($response, 'error.php', [
                'page_title' => 'Not found',
                'error_message' => 'Order not found.',
            ]);
        }
        if (!$this->canTransition($user, $order)) {
            return $this->render($response, 'error.php', [
                'page_title' => 'Forbidden',
                'error_message' => 'You cannot change this order.',
            ]);
        }
        return $this->render($response, 'order_lifecycle.php', [
            'page_title' => 'Order lifecycle',
            'order' => $order,
            'items' => OrderRepository::items($id),
            'events' => OrderRepository::events($id),
            'role' => $user['role'],
        ]);
    }

    public function transition(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/orders');
        }
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canTransition($user, $order)) {
            SessionManager::flash('error', 'Cannot change this order.');
            return $this->redirect($response, '/orders');
        }
        $to = (string)$this->input($request, 'status');
        $allowedTargets = match ($user['role']) {
            'admin' => ['paid', 'shipped', 'delivered', 'cancelled', 'refunded'],
            'seller' => ['shipped', 'delivered'],
            default => ['cancelled'],
        };
        if (!in_array($to, $allowedTargets, true)) {
            SessionManager::flash('error', 'Status transition not allowed for your role.');
            return $this->redirect($response, '/orders/' . $id . '/lifecycle');
        }
        $ok = OrderRepository::transition($id, $to, (int)$user['id'], (string)$this->input($request, 'note', ''));
        if (!$ok) {
            SessionManager::flash('error', 'Invalid transition for current order state.');
            return $this->redirect($response, '/orders/' . $id . '/lifecycle');
        }
        AuditRepository::log((int)$user['id'], 'order_transition', 'order', (string)$id, ['to' => $to]);
        SessionManager::flash('success', 'Order moved to ' . $to . '.');
        return $this->redirect($response, '/orders/' . $id . '/lifecycle');
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        return $this->json($response, ['orders' => OrderRepository::all()]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($this->input($request, 'order_id') ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canTransition($user, $order)) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        return $this->json($response, ['order' => $order, 'events' => OrderRepository::events($id)]);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canTransition($user, $order)) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        $data = $this->jsonBody($request);
        $to = (string)($data['status'] ?? '');
        $ok = OrderRepository::transition($id, $to, (int)$user['id'], (string)($data['note'] ?? ''));
        if (!$ok) {
            return $this->json($response, ['error' => 'invalid_transition'], 400);
        }
        AuditRepository::log((int)$user['id'], 'order_transition_api', 'order', (string)$id, ['to' => $to]);
        return $this->json($response, ['order' => OrderRepository::find($id)]);
    }

    private function canTransition(array $user, ?array $order): bool
    {
        if (!$order) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        if ($user['role'] === 'seller') {
            foreach (OrderRepository::items((int)$order['id']) as $item) {
                if ((int)$item['seller_id'] === (int)$user['id']) {
                    return true;
                }
            }
            return false;
        }
        return (int)$order['user_id'] === (int)$user['id'];
    }
}