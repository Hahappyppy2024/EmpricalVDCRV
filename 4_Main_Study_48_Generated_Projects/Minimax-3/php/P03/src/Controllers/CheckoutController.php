<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\CartRepository;
use Shop\Models\OrderRepository;
use Shop\Models\CustomerDataRepository;
use Shop\Models\AuditRepository;
use Shop\Support\Mailer;
use Shop\Support\Validator;

final class CheckoutController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        $cart = CartRepository::cartForUser((int)$user['id']);
        if (!$cart['items']) {
            SessionManager::flash('notice', 'Your cart is empty.');
            return $this->redirect($response, '/cart');
        }
        return $this->render($response, 'checkout.php', [
            'page_title' => 'Checkout',
            'cart' => $cart,
            'addresses' => CustomerDataRepository::addresses((int)$user['id']),
        ]);
    }

    public function place(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/checkout');
        }
        $cart = CartRepository::cartForUser((int)$user['id']);
        if (!$cart['items']) {
            SessionManager::flash('error', 'Cart is empty.');
            return $this->redirect($response, '/cart');
        }
        $errors = Validator::requireFields([
            'full_name' => (string)$this->input($request, 'full_name'),
            'line1' => (string)$this->input($request, 'line1'),
            'city' => (string)$this->input($request, 'city'),
            'postal_code' => (string)$this->input($request, 'postal_code'),
        ], [
            'full_name' => 'Full name', 'line1' => 'Address line 1',
            'city' => 'City', 'postal_code' => 'Postal code',
        ]);
        if ($errors) {
            SessionManager::flash('error', implode(' ', array_values($errors)));
            return $this->redirect($response, '/checkout');
        }
        $addressId = CustomerDataRepository::addAddress((int)$user['id'], [
            'full_name' => (string)$this->input($request, 'full_name'),
            'line1' => (string)$this->input($request, 'line1'),
            'line2' => (string)$this->input($request, 'line2'),
            'city' => (string)$this->input($request, 'city'),
            'region' => (string)$this->input($request, 'region'),
            'postal_code' => (string)$this->input($request, 'postal_code'),
            'country' => (string)$this->input($request, 'country'),
            'is_default' => 0,
        ]);
        $result = OrderRepository::create(
            (int)$user['id'],
            CartRepository::ensureCart((int)$user['id']),
            $cart,
            [
                'shipping_address_id' => $addressId,
                'payment_method' => (string)$this->input($request, 'payment_method', 'card'),
                'payment_token' => (string)$this->input($request, 'payment_token', 'tok_ok'),
            ]
        );
        if (!empty($result['payment_declined'])) {
            SessionManager::flash('error', 'Payment declined: ' . ($result['message'] ?? 'try again.'));
            return $this->redirect($response, '/checkout');
        }
        if (!$result || empty($result['order_id'])) {
            SessionManager::flash('error', 'Checkout failed. Please try again.');
            return $this->redirect($response, '/checkout');
        }
        AuditRepository::log((int)$user['id'], 'order_create', 'order', (string)$result['order_id'], ['reference' => $result['reference']]);
        Mailer::send((string)$user['email'], 'Order ' . $result['reference'] . ' confirmed', "Thanks for your order. Reference: {$result['reference']}");
        SessionManager::flash('success', 'Order ' . $result['reference'] . ' placed.');
        return $this->redirect($response, '/orders/' . $result['order_id']);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        return $this->json($response, ['cart' => CartRepository::cartForUser((int)$user['id'])]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        $data = $this->jsonBody($request);
        $cart = CartRepository::cartForUser((int)$user['id']);
        if (!$cart['items']) {
            return $this->json($response, ['error' => 'empty_cart'], 400);
        }
        $required = ['full_name', 'line1', 'city', 'postal_code'];
        foreach ($required as $r) {
            if (empty($data[$r])) {
                return $this->json($response, ['error' => 'missing_field', 'field' => $r], 400);
            }
        }
        $addressId = CustomerDataRepository::addAddress((int)$user['id'], [
            'full_name' => (string)$data['full_name'],
            'line1' => (string)$data['line1'],
            'line2' => (string)($data['line2'] ?? ''),
            'city' => (string)$data['city'],
            'region' => (string)($data['region'] ?? ''),
            'postal_code' => (string)$data['postal_code'],
            'country' => (string)($data['country'] ?? 'US'),
        ]);
        $result = OrderRepository::create(
            (int)$user['id'],
            CartRepository::ensureCart((int)$user['id']),
            $cart,
            [
                'shipping_address_id' => $addressId,
                'payment_method' => (string)($data['payment_method'] ?? 'card'),
                'payment_token' => (string)($data['payment_token'] ?? 'tok_ok'),
            ]
        );
        if (!empty($result['payment_declined'])) {
            return $this->json($response, ['error' => 'payment_declined', 'message' => $result['message']], 402);
        }
        if (!$result || empty($result['order_id'])) {
            return $this->json($response, ['error' => 'checkout_failed'], 400);
        }
        AuditRepository::log((int)$user['id'], 'order_create_api', 'order', (string)$result['order_id']);
        return $this->json($response, $result, 201);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'customer');
        $id = (int)($args['id'] ?? 0);
        $order = OrderRepository::find($id);
        if (!$order || (int)$order['user_id'] !== (int)$user['id']) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }
        $data = $this->jsonBody($request);
        $newAddress = $data['address'] ?? null;
        if (!is_array($newAddress)) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        foreach (['full_name', 'line1', 'city', 'postal_code'] as $r) {
            if (empty($newAddress[$r])) {
                return $this->json($response, ['error' => 'missing_field', 'field' => $r], 400);
            }
        }
        CustomerDataRepository::addAddress((int)$user['id'], $newAddress + ['is_default' => 0]);
        return $this->json($response, ['order' => $order]);
    }
}