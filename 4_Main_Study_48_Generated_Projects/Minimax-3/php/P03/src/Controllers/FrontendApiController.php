<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\OrderRepository;
use Shop\Models\CartRepository;
use Shop\Support\Payment;

final class FrontendApiController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = SessionManager::user();
        $orders = $user ? OrderRepository::forUser((int)$user['id']) : [];
        $cart = $user ? CartRepository::cartForUser((int)$user['id']) : ['items' => []];
        return $this->render($response, 'frontend_api.php', [
            'page_title' => 'Frontend API',
            'orders' => $orders,
            'cart' => $cart,
            'role' => $user['role'] ?? 'visitor',
        ]);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = SessionManager::user();
        $cart = $user ? CartRepository::cartForUser((int)$user['id']) : ['items' => []];
        return $this->json($response, [
            'session_state' => $user ? 'authenticated' : 'guest',
            'cart_count' => count($cart['items']),
            'orders_count' => $user ? count(OrderRepository::forUser((int)$user['id'])) : 0,
        ]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $data = $this->jsonBody($request);
        $intent = (string)($data['intent'] ?? '');
        switch ($intent) {
            case 'validate_payment':
                $preview = Payment::charge(
                    (string)($data['method'] ?? 'card'),
                    (string)($data['token'] ?? 'tok_ok'),
                    (int)($data['amount_cents'] ?? 0)
                );
                return $this->json($response, ['preview' => $preview]);
            case 'validate_form':
                $errors = [];
                foreach (['full_name', 'line1', 'city', 'postal_code'] as $f) {
                    if (empty($data[$f])) {
                        $errors[] = $f;
                    }
                }
                return $this->json($response, ['valid' => empty($errors), 'errors' => $errors]);
            case 'async_order':
                $orderId = (int)($data['order_id'] ?? 0);
                $order = OrderRepository::find($orderId);
                return $this->json($response, ['order' => $order]);
            default:
                return $this->json($response, ['error' => 'invalid_intent'], 400);
        }
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$order) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }
        return $this->json($response, ['order' => $order, 'state' => 'pending_polling']);
    }
}