<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\DomainException;
use Shop\Service\AccountService;
use Shop\Service\AdminService;
use Shop\Service\CartService;
use Shop\Service\CatalogManagementService;
use Shop\Service\CatalogService;
use Shop\Service\CheckoutService;
use Shop\Service\CustomerDataService;
use Shop\Service\FrontendApiService;
use Shop\Service\InventoryService;
use Shop\Service\OrderService;
use Shop\Service\ReportService;
use Shop\Service\ReviewService;
use Shop\SessionManager;

/**
 * The /api/shop/* JSON contract for all thirteen use cases.
 *
 * Every endpoint returns {success:bool, data:..., message:...} on success and
 * {success:false, error:..., errors:{...}} on failure. Errors are deterministic
 * and never expose stack traces.
 */
final class ApiController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private CatalogService $catalog,
        private ReviewService $reviews,
        private CatalogManagementService $catalogManagement,
        private CartService $cart,
        private CheckoutService $checkout,
        private OrderService $orders,
        private InventoryService $inventory,
        private AdminService $admin,
        private CustomerDataService $customerData,
        private ReportService $reports,
        private FrontendApiService $frontend,
        private SessionManager $sessions
    ) {
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-01 Accounts                                                     */
    /* ------------------------------------------------------------------ */

    public function accounts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            $user = $this->user($request);
            if ($user === null) {
                throw new DomainException('Authentication required.', 401);
            }
            if ($user['role'] === 'admin') {
                return $this->ok($response, ['accounts' => array_map($this->sanitize(...), $this->accounts->listUsers())]);
            }
            return $this->ok($response, ['account' => $this->sanitize($user)]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $action = (string) ($data['action'] ?? 'register');

            if ($action === 'logout') {
                $token = $_COOKIE[SessionManager::COOKIE] ?? '';
                if ($token !== '') {
                    $this->sessions->destroy($token);
                }
                return $this->ok($response, ['message' => 'Signed out.']);
            }

            if ($action === 'login') {
                $user = $this->accounts->login((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''), (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
                $session = $this->sessions->start((int) $user['id'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? ''));
                return $this->ok($response, ['account' => $this->sanitize($user), 'session' => $session]);
            }

            $user = $this->accounts->register($data);
            $session = $this->sessions->start((int) $user['id'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? ''));
            return $this->ok($response, ['account' => $this->sanitize($user), 'session' => $session], 201);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function accountsUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $id = $this->param($args, 'id');
        $data = $this->body($request);

        if ((int) $user['id'] === $id) {
            return $this->ok($response, ['account' => $this->sanitize($this->accounts->updateProfile($user, $data))]);
        }
        if ($user['role'] === 'admin') {
            return $this->ok($response, ['account' => $this->sanitize($this->accounts->updateUser($user, $id, $data))]);
        }
        throw new DomainException('You cannot modify another account.', 403);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-02 Catalog search                                               */
    /* ------------------------------------------------------------------ */

    public function catalogSearch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            $user = $this->user($request);
            $query = $this->query($request);
            if (($query['action'] ?? '') === 'history' && $user !== null) {
                return $this->ok($response, ['saved' => $this->catalog->savedSearches($user)]);
            }
            $data = $this->catalog->search($query);
            return $this->ok($response, [
                'results' => $data,
                'count' => count($data),
                'categories' => $this->catalog->categories(),
                'filters' => $query,
            ]);
        }

        if ($method === 'POST') {
            $user = $this->requireUser($request);
            $data = $this->body($request);
            $filters = $data['filters'] ?? $data;
            $results = $this->catalog->search($filters);
            $saved = null;
            if (!empty($data['name'])) {
                $saved = $this->catalog->saveSearch($user, (string) $data['name'], (string) ($data['query'] ?? ''), $filters);
            }
            return $this->ok($response, [
                'results' => $results,
                'count' => count($results),
                'saved' => $saved,
            ]);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function catalogSearchUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        $filters = $data['filters'] ?? [];
        return $this->ok($response, [
            'saved' => $this->catalog->updateSearch($user, $this->param($args, 'id'), (string) ($data['name'] ?? ''), (string) ($data['query'] ?? ''), $filters),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-03 Reviews                                                      */
    /* ------------------------------------------------------------------ */

    public function reviews(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            $query = $this->query($request);
            $user = $this->user($request);
            if (($query['status'] ?? '') === 'pending' && in_array($user['role'] ?? '', ['moderator', 'admin'], true)) {
                return $this->ok($response, ['reviews' => $this->reviews->pending()]);
            }
            $productId = (int) ($query['product_id'] ?? 0);
            if ($productId > 0) {
                return $this->ok($response, [
                    'reviews' => $this->catalog->productReviews($productId),
                    'summary' => $this->catalog->reviewSummary($productId),
                ]);
            }
            return $this->ok($response, ['reviews' => $this->reviews->recent()]);
        }

        if ($method === 'POST') {
            $user = $this->requireUser($request);
            $data = $this->body($request);
            $review = $this->reviews->create($user, (int) ($data['product_id'] ?? 0), $data);
            return $this->ok($response, ['review' => $review], 201);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function reviewUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $moderator = $this->requireRole($request, ['moderator', 'admin']);
        $data = $this->body($request);
        return $this->ok($response, [
            'review' => $this->reviews->moderate($moderator, $this->param($args, 'id'), (string) ($data['decision'] ?? '')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-04 Catalog management                                           */
    /* ------------------------------------------------------------------ */

    public function catalogManagement(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            $user = $this->requireRole($request, ['seller', 'admin']);
            return $this->ok($response, [
                'products' => $this->catalogManagement->list($user, $this->query($request)),
                'categories' => $this->catalogManagement->categories(),
            ]);
        }

        if ($method === 'POST') {
            $user = $this->requireRole($request, ['seller', 'admin']);
            $data = $this->body($request);
            $upload = isset($_FILES['image']) ? ['image' => $_FILES['image']] : null;
            $product = $this->catalogManagement->create($user, $data, $upload);
            return $this->ok($response, ['product' => $product], 201);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function catalogManagementUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $upload = isset($_FILES['image']) ? ['image' => $_FILES['image']] : null;
        return $this->ok($response, [
            'product' => $this->catalogManagement->update($user, $this->param($args, 'id'), $this->body($request), $upload),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-05 Shopping cart                                                */
    /* ------------------------------------------------------------------ */

    public function shoppingCart(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            return $this->ok($response, $this->cart->get($user));
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            if (($data['action'] ?? '') === 'remove') {
                return $this->ok($response, $this->cart->remove($user, (int) ($data['cart_id'] ?? 0)));
            }
            $cart = $this->cart->add($user, (int) ($data['product_id'] ?? 0), (int) ($data['quantity'] ?? 1));
            return $this->ok($response, $cart, 201);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function shoppingCartUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        return $this->ok($response, $this->cart->updateQuantity($user, $this->param($args, 'id'), (int) ($data['quantity'] ?? 1)));
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-06 Checkout                                                     */
    /* ------------------------------------------------------------------ */

    public function checkout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            return $this->ok($response, $this->checkout->data($user));
        }

        if ($method === 'POST') {
            $order = $this->checkout->placeOrder($user, $this->body($request));
            return $this->ok($response, ['order' => $order], 201);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function checkoutUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        return $this->ok($response, [
            'order' => $this->checkout->updateShippingAddress($user, $this->param($args, 'id'), $this->body($request)),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-07 Order access                                                 */
    /* ------------------------------------------------------------------ */

    public function orderAccess(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            $query = $this->query($request);
            if (!empty($query['id'])) {
                return $this->ok($response, ['order' => $this->orders->detail($user, (int) $query['id'])]);
            }
            if (!empty($query['number'])) {
                return $this->ok($response, ['order' => $this->orders->byNumber($user, (string) $query['number'])]);
            }
            return $this->ok($response, ['orders' => $this->orders->list($user, $query)]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            if (!empty($data['id'])) {
                return $this->ok($response, ['order' => $this->orders->detail($user, (int) $data['id'])]);
            }
            if (!empty($data['number'])) {
                return $this->ok($response, ['order' => $this->orders->byNumber($user, (string) $data['number'])]);
            }
            throw new DomainException('An order id or number is required.', 422);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function orderAccessUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        throw new DomainException('PATCH is not supported for order access (read-only workflow).', 405);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-08 Order lifecycle                                              */
    /* ------------------------------------------------------------------ */

    public function orderLifecycle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            return $this->ok($response, [
                'orders' => $this->orders->list($user, $this->query($request)),
                'statuses' => \Shop\Service\OrderService::STATUSES,
            ]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $order = $this->orders->transition($user, (int) ($data['order_id'] ?? 0), (string) ($data['status'] ?? ''), (string) ($data['comment'] ?? ''));
            return $this->ok($response, ['order' => $order]);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function orderLifecycleUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        return $this->ok($response, [
            'order' => $this->orders->transition($user, $this->param($args, 'id'), (string) ($data['status'] ?? ''), (string) ($data['comment'] ?? '')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-09 Inventory                                                    */
    /* ------------------------------------------------------------------ */

    public function inventory(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireRole($request, ['seller', 'admin']);

        if ($method === 'GET') {
            return $this->ok($response, ['products' => $this->inventory->list($user, $this->query($request))]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $product = $this->inventory->adjust($user, (int) ($data['product_id'] ?? 0), (int) ($data['delta'] ?? 0), (string) ($data['reason'] ?? ''));
            return $this->ok($response, ['product' => $product]);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function inventoryUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $data = $this->body($request);
        return $this->ok($response, [
            'product' => $this->inventory->restock($user, $this->param($args, 'id'), (int) ($data['quantity'] ?? 0)),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-10 Seller and administrator operations                          */
    /* ------------------------------------------------------------------ */

    public function sellerAdminOperations(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireRole($request, ['seller', 'admin']);

        if ($method === 'GET') {
            return $this->ok($response, ['stats' => $this->admin->dashboardStats($user)]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $operation = (string) ($data['operation'] ?? '');
            return match ($operation) {
                'create_promotion' => $this->ok($response, ['promotion' => $this->admin->createPromotion($user, $data)], 201),
                'set_setting' => $this->ok($response, ['settings' => $this->admin->setSetting($user, (string) ($data['key'] ?? ''), (string) ($data['value'] ?? ''))]),
                'manage_user' => $this->ok($response, ['user' => $this->admin->manageUser($user, (int) ($data['id'] ?? 0), $data)]),
                default => throw new DomainException('Unknown operation. Use create_promotion, set_setting or manage_user.', 422),
            };
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function sellerAdminOperationsUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $data = $this->body($request);
        $operation = (string) ($data['operation'] ?? 'toggle_promotion');
        return match ($operation) {
            'toggle_promotion' => $this->ok($response, ['promotion' => $this->admin->togglePromotion($user, $this->param($args, 'id'))]),
            'manage_user' => $this->ok($response, ['user' => $this->admin->manageUser($user, $this->param($args, 'id'), $data)]),
            default => throw new DomainException('Unknown operation. Use toggle_promotion or manage_user.', 422),
        };
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-11 Customer data                                                */
    /* ------------------------------------------------------------------ */

    public function customerData(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            $query = $this->query($request);
            if ($user['role'] === 'admin' && ($query['action'] ?? '') === 'list') {
                return $this->ok($response, ['customers' => $this->customerData->adminList()]);
            }
            return $this->ok($response, ['profile' => $this->customerData->profile($user)]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $action = (string) ($data['action'] ?? 'address');
            return match ($action) {
                'address' => $this->ok($response, ['address' => $this->customerData->createAddress($user, $data)], 201),
                'delete_address' => (function () use ($user, $data, $response) {
                    $this->customerData->deleteAddress($user, (int) ($data['address_id'] ?? 0));
                    return $this->ok($response, ['message' => 'Address removed.']);
                })(),
                'update_profile' => $this->ok($response, ['profile' => $this->customerData->updateProfile($user, $data)]),
                default => throw new DomainException('Unknown action.', 422),
            };
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function customerDataUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $id = $this->param($args, 'id');
        $data = $this->body($request);

        if ((int) $user['id'] === $id) {
            return $this->ok($response, ['profile' => $this->customerData->updateProfile($user, $data)]);
        }
        return $this->ok($response, [
            'profile' => $this->customerData->adminUpdate($user, $id, $data),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-12 Reports                                                      */
    /* ------------------------------------------------------------------ */

    public function reports(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireRole($request, ['seller', 'admin']);

        if ($method === 'GET') {
            $query = $this->query($request);
            return $this->ok($response, [
                'report' => $this->reports->generate($user, (string) ($query['type'] ?? 'sales'), $query),
            ]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $filters = $data['filters'] ?? $data;
            return $this->ok($response, [
                'report' => $this->reports->generate($user, (string) ($data['type'] ?? 'sales'), $filters),
            ]);
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function reportsUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        throw new DomainException('PATCH is not supported for reports (read/export workflow).', 405);
    }

    /* ------------------------------------------------------------------ */
    /* SHOP-13 Frontend API integration                                     */
    /* ------------------------------------------------------------------ */

    public function frontendApi(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $user = $this->requireUser($request);

        if ($method === 'GET') {
            return $this->ok($response, [
                'ws_url' => $this->frontend->wsUrl(),
                'clients' => $this->frontend->clients($user),
                'order_states' => $this->frontend->orderStates($user),
            ]);
        }

        if ($method === 'POST') {
            $data = $this->body($request);
            $action = (string) ($data['action'] ?? 'register_client');
            return match ($action) {
                'register_client' => $this->ok($response, ['client' => $this->frontend->registerClient($user, (string) ($data['name'] ?? ''))], 201),
                'order_states' => $this->ok($response, ['order_states' => $this->frontend->orderStates($user)]),
                'payment_states' => $this->ok($response, ['payment_states' => $this->frontend->paymentStates($user, (int) ($data['order_id'] ?? 0))]),
                default => throw new DomainException('Unknown action. Use register_client, order_states or payment_states.', 422),
            };
        }

        throw new DomainException('Method not allowed.', 405);
    }

    public function frontendApiUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        return $this->ok($response, [
            'client' => $this->frontend->updateClient($user, $this->param($args, 'id'), (string) ($data['name'] ?? ''), (array) ($data['prefs'] ?? [])),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* helpers                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $data
     */
    private function ok(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        return $this->json($response, ['success' => true, 'data' => $data], $status);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function sanitize(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
