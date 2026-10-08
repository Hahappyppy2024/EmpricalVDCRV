<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\DomainException;
use Shop\Service\CartService;
use Shop\Service\CheckoutService;
use Shop\View;

/**
 * SHOP-05 Shopping cart and SHOP-06 Checkout browser workflows.
 */
final class CartController extends Controller
{
    public function __construct(
        private View $view,
        private CartService $cart,
        private CheckoutService $checkout
    ) {
    }

    public function cartPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        return $this->html($response, $this->view->render('cart', $this->cart->get($user)));
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        try {
            $this->cart->add($user, (int) ($data['product_id'] ?? 0), (int) ($data['quantity'] ?? 1));
            return $this->redirect($response, '/cart' . flash_query(null, 'Item added to your cart.'));
        } catch (\Throwable $e) {
            $back = isset($data['return_to']) && $data['return_to'] !== '' ? (string) $data['return_to'] : '/cart';
            return $this->redirect($response, $back . flash_query($this->safeMessage($e)));
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        try {
            $this->cart->updateQuantity($user, $this->param($args, 'id'), (int) ($data['quantity'] ?? 1));
        } catch (\Throwable $e) {
            // deterministic stable redirect below
        }
        return $this->redirect($response, '/cart');
    }

    public function remove(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        try {
            $this->cart->remove($user, $this->param($args, 'id'));
        } catch (\Throwable $e) {
            // stable redirect below
        }
        return $this->redirect($response, '/cart');
    }

    public function checkoutPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->checkout->data($user);
        if ($data['items'] === []) {
            return $this->redirect($response, '/cart' . flash_query('Your cart is empty.'));
        }
        return $this->html($response, $this->view->render('checkout', $data));
    }

    public function checkout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        try {
            $order = $this->checkout->placeOrder($user, $this->body($request));
            return $this->redirect($response, '/account/orders/' . $order['id'] . flash_query(null, 'Order ' . $order['number'] . ' placed and paid.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/checkout' . flash_query($this->safeMessage($e)));
        }
    }

    private function safeMessage(\Throwable $e): string
    {
        return ($e instanceof DomainException || $e instanceof \Shop\ValidationException) ? $e->getMessage() : 'Something went wrong.';
    }
}
