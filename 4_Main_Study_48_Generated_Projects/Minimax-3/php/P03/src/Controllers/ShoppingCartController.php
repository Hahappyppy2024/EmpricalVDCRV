<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\CartRepository;
use Shop\Models\ProductRepository;
use Shop\Models\AuditRepository;

final class ShoppingCartController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        $cart = CartRepository::cartForUser((int)$user['id']);
        return $this->render($response, 'cart.php', [
            'page_title' => 'Shopping cart',
            'cart' => $cart,
        ]);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        return $this->json($response, CartRepository::cartForUser((int)$user['id']));
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        $cartId = CartRepository::ensureCart((int)$user['id']);
        $data = $this->jsonBody($request);
        $productId = (int)($data['product_id'] ?? 0);
        $quantity = (int)($data['quantity'] ?? 1);
        if ($productId <= 0 || $quantity <= 0) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        if (!CartRepository::add($cartId, $productId, $quantity)) {
            return $this->json($response, ['error' => 'product_unavailable'], 404);
        }
        AuditRepository::log((int)$user['id'], 'cart_add', 'cart', (string)$cartId, ['product_id' => $productId, 'quantity' => $quantity]);
        return $this->json($response, ['cart' => CartRepository::cartForUser((int)$user['id'])], 201);
    }

    public function apiUpdate(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'customer');
        $cartId = CartRepository::ensureCart((int)$user['id']);
        $itemId = (int)($args['id'] ?? 0);
        $data = $this->jsonBody($request);
        $quantity = (int)($data['quantity'] ?? 0);
        if (!CartRepository::updateQuantity($cartId, $itemId, $quantity, (int)$user['id'])) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        AuditRepository::log((int)$user['id'], 'cart_update', 'cart_item', (string)$itemId, ['quantity' => $quantity]);
        return $this->json($response, ['cart' => CartRepository::cartForUser((int)$user['id'])]);
    }

    public function updateItem(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'customer');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/cart');
        }
        $cartId = CartRepository::ensureCart((int)$user['id']);
        $itemId = (int)($args['id'] ?? 0);
        CartRepository::updateQuantity($cartId, $itemId, (int)$this->input($request, 'quantity'), (int)$user['id']);
        return $this->redirect($response, '/cart');
    }

    public function removeItem(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'customer');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/cart');
        }
        $cartId = CartRepository::ensureCart((int)$user['id']);
        $itemId = (int)($args['id'] ?? 0);
        CartRepository::remove($cartId, $itemId, (int)$user['id']);
        AuditRepository::log((int)$user['id'], 'cart_remove', 'cart_item', (string)$itemId);
        return $this->redirect($response, '/cart');
    }

    public function applyPromotion(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'customer');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/cart');
        }
        $cartId = CartRepository::ensureCart((int)$user['id']);
        $code = trim((string)$this->input($request, 'code'));
        if (!CartRepository::applyPromotion($cartId, $code)) {
            SessionManager::flash('error', 'Promotion code not found or inactive.');
            return $this->redirect($response, '/cart');
        }
        SessionManager::flash('success', 'Promotion applied.');
        return $this->redirect($response, '/cart');
    }
}