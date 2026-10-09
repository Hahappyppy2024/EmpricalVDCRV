<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\OrderRepository;

final class OrderAccessController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $orders = match ($user['role']) {
            'admin' => OrderRepository::all(),
            'seller' => OrderRepository::forSeller((int)$user['id']),
            default => OrderRepository::forUser((int)$user['id']),
        };
        return $this->render($response, 'orders.php', [
            'page_title' => 'Orders',
            'orders' => $orders,
            'role' => $user['role'],
        ]);
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canView($user, $order)) {
            return $this->render($response, 'error.php', [
                'page_title' => 'Forbidden',
                'error_message' => 'You cannot view this order.',
            ]);
        }
        return $this->render($response, 'order.php', [
            'page_title' => 'Order ' . ($order['reference'] ?? ''),
            'order' => $order,
            'items' => OrderRepository::items((int)$order['id']),
            'events' => OrderRepository::events((int)$order['id']),
            'role' => $user['role'],
        ]);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $orders = match ($user['role']) {
            'admin' => OrderRepository::all(),
            'seller' => OrderRepository::forSeller((int)$user['id']),
            default => OrderRepository::forUser((int)$user['id']),
        };
        return $this->json($response, ['orders' => $orders]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($this->input($request, 'order_id') ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canView($user, $order)) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        return $this->json($response, ['order' => $order, 'items' => OrderRepository::items($id)]);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$this->canView($user, $order)) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        return $this->json($response, ['order' => $order]);
    }

    private function canView(array $user, ?array $order): bool
    {
        if (!$order) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        if ((int)$order['user_id'] === (int)$user['id']) {
            return true;
        }
        if ($user['role'] === 'seller') {
            foreach (OrderRepository::items((int)$order['id']) as $item) {
                if ((int)$item['seller_id'] === (int)$user['id']) {
                    return true;
                }
            }
        }
        return false;
    }
}