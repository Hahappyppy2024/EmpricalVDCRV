<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\Service\CustomerDataService;
use Shop\Service\OrderService;
use Shop\View;

/**
 * SHOP-07/08 customer order pages, SHOP-11 customer data pages.
 */
final class AccountController extends Controller
{
    public function __construct(
        private View $view,
        private CustomerDataService $customerData,
        private OrderService $orders
    ) {
    }

    public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        return $this->html($response, $this->view->render('account/profile', [
            'profile' => $this->customerData->profile($user),
        ]));
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        try {
            $this->customerData->updateProfile($user, $this->body($request));
            return $this->redirect($response, '/account' . flash_query(null, 'Profile updated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/account' . flash_query($e->getMessage()));
        }
    }

    public function addresses(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        return $this->html($response, $this->view->render('account/addresses', [
            'addresses' => $this->customerData->addresses($user),
        ]));
    }

    public function addAddress(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        try {
            $this->customerData->createAddress($user, $this->body($request));
            return $this->redirect($response, '/account/addresses' . flash_query(null, 'Address added.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/account/addresses' . flash_query($e->getMessage()));
        }
    }

    public function deleteAddress(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        try {
            $this->customerData->deleteAddress($user, $this->param($args, 'id'));
            return $this->redirect($response, '/account/addresses' . flash_query(null, 'Address removed.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/account/addresses' . flash_query($e->getMessage()));
        }
    }

    public function orders(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        return $this->html($response, $this->view->render('account/orders', [
            'orders' => $this->orders->list($user, $this->query($request)),
        ]));
    }

    public function orderDetail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $order = $this->orders->detail($user, $this->param($args, 'id'));
        $order['allowed_transitions'] = $this->orders->allowedTransitions($user, $order);
        return $this->html($response, $this->view->render('account/order', [
            'order' => $order,
        ]));
    }

    public function transition(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        try {
            $this->orders->transition($user, $this->param($args, 'id'), (string) ($data['status'] ?? ''), (string) ($data['comment'] ?? ''));
            return $this->redirect($response, '/account/orders/' . $this->param($args, 'id') . flash_query(null, 'Order status updated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/account/orders/' . $this->param($args, 'id') . flash_query($e->getMessage()));
        }
    }
}
