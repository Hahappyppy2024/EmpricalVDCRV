<?php

declare(strict_types=1);

namespace Shop;

use Shop\Controller\AccountController;
use Shop\Controller\AdminController;
use Shop\Controller\ApiController;
use Shop\Controller\AuthController;
use Shop\Controller\CartController;
use Shop\Controller\PageController;
use Shop\Controller\SellerController;
use Shop\Handler\AppErrorHandler;
use Shop\Log\FileLogger;
use Shop\Middleware\CsrfMiddleware;
use Shop\Middleware\PageAuthMiddleware;
use Shop\Middleware\PageRoleMiddleware;
use Shop\Middleware\SecurityHeadersMiddleware;
use Shop\Middleware\SessionMiddleware;
use Shop\Repository\AddressRepository;
use Shop\Repository\AuditRepository;
use Shop\Repository\BroadcastRepository;
use Shop\Repository\CartRepository;
use Shop\Repository\CategoryRepository;
use Shop\Repository\ClientRepository;
use Shop\Repository\OrderRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\PromotionRepository;
use Shop\Repository\ReviewRepository;
use Shop\Repository\SearchRepository;
use Shop\Repository\SettingRepository;
use Shop\Repository\StockRepository;
use Shop\Repository\UserRepository;
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
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

final class App
{
    public static function create(): SlimApp
    {
        $root = dirname(__DIR__);
        Config::load($root);

        foreach (['storage/logs', 'public/uploads', 'data'] as $dir) {
            $absolute = Config::absolute($dir);
            if (!is_dir($absolute)) {
                mkdir($absolute, 0777, true);
            }
        }

        $pdo = Database::connect();
        Database::migrate($pdo);

        $settings = (new SettingRepository($pdo))->all();

        $view = new View($root . '/views', [
            'appName' => $settings['shop_name'] ?? 'P03 E-commerce System',
            'currency' => $settings['currency'] ?? 'USD',
            'user' => null,
            'csrf' => '',
            'wsUrl' => (string) Config::get('WS_URL', 'ws://127.0.0.1:8081'),
            'shop' => $settings,
        ]);

        /* repositories */
        $userRepo = new UserRepository($pdo);
        $categoryRepo = new CategoryRepository($pdo);
        $productRepo = new ProductRepository($pdo);
        $reviewRepo = new ReviewRepository($pdo);
        $cartRepo = new CartRepository($pdo);
        $addressRepo = new AddressRepository($pdo);
        $orderRepo = new OrderRepository($pdo);
        $promoRepo = new PromotionRepository($pdo);
        $settingRepo = new SettingRepository($pdo);
        $stockRepo = new StockRepository($pdo);
        $auditRepo = new AuditRepository($pdo);
        $broadcastRepo = new BroadcastRepository($pdo);
        $searchRepo = new SearchRepository($pdo);
        $clientRepo = new ClientRepository($pdo);

        /* infrastructure services */
        $sessionManager = new SessionManager($pdo);
        $auth = new Auth($pdo);
        $mail = new MailService($pdo);
        $payment = new PaymentService();
        $imageStore = new ImageStore(Config::absolute((string) Config::get('UPLOAD_DIR', 'public/uploads')));

        /* domain services */
        $accountService = new AccountService($pdo, $auth, $userRepo, $mail);
        $cartService = new CartService($cartRepo, $productRepo, $settingRepo);
        $catalogService = new CatalogService($productRepo, $categoryRepo, $reviewRepo, $searchRepo);
        $reviewService = new ReviewService($reviewRepo, $productRepo);
        $catalogManagementService = new CatalogManagementService($productRepo, $categoryRepo, $imageStore, $auditRepo);
        $checkoutService = new CheckoutService($cartRepo, $addressRepo, $orderRepo, $stockRepo, $productRepo, $promoRepo, $settingRepo, $payment, $broadcastRepo, $cartService);
        $orderService = new OrderService($orderRepo, $productRepo, $stockRepo, $broadcastRepo);
        $inventoryService = new InventoryService($productRepo, $stockRepo, $auditRepo);
        $adminService = new AdminService($userRepo, $promoRepo, $settingRepo, $auditRepo, $orderRepo, $productRepo, $stockRepo);
        $customerDataService = new CustomerDataService($userRepo, $addressRepo);
        $reportService = new ReportService($orderRepo, $stockRepo, $userRepo);
        $frontendService = new FrontendApiService($clientRepo, $orderRepo);

        /* controllers */
        $pageController = new PageController($view, $catalogService, $cartService, $reviewService);
        $authController = new AuthController($view, $accountService, $sessionManager);
        $cartController = new CartController($view, $cartService, $checkoutService);
        $accountController = new AccountController($view, $customerDataService, $orderService);
        $sellerController = new SellerController($view, $catalogManagementService, $inventoryService, $orderService, $adminService, $reportService);
        $adminController = new AdminController($view, $adminService, $accountService, $reviewService);
        $apiController = new ApiController(
            $accountService,
            $catalogService,
            $reviewService,
            $catalogManagementService,
            $cartService,
            $checkoutService,
            $orderService,
            $inventoryService,
            $adminService,
            $customerDataService,
            $reportService,
            $frontendService,
            $sessionManager
        );

        $app = AppFactory::create();

        /* middleware stack: first added runs innermost, last added is outermost.
           ErrorMiddleware must wrap everything so no exception escapes. */
        $app->addBodyParsingMiddleware();
        $app->add(new CsrfMiddleware());
        $app->add(new SessionMiddleware($pdo, $view));
        $logger = new FileLogger(Config::absolute((string) Config::get('LOG_FILE', 'storage/logs/error.log')));
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(new AppErrorHandler($app->getCallableResolver(), $app->getResponseFactory(), $logger));
        $app->add(new SecurityHeadersMiddleware());

        /* public pages */
        $app->get('/', [$pageController, 'home']);
        $app->get('/catalog', [$pageController, 'catalog']);
        $app->get('/products/{id:\d+}', [$pageController, 'product']);
        $app->get('/products/{id:\d+}/{slug}', [$pageController, 'product']);
        $app->post('/reviews', [$pageController, 'submitReview'])->add(new PageAuthMiddleware());
        $app->get('/health', function ($request, $response) {
            $response->getBody()->write(json_encode(['status' => 'ok', 'service' => 'P03 E-commerce System']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        /* SHOP-01 accounts */
        $app->get('/login', [$authController, 'loginPage']);
        $app->post('/login', [$authController, 'login']);
        $app->get('/register', [$authController, 'registerPage']);
        $app->post('/register', [$authController, 'register']);
        $app->get('/forgot', [$authController, 'forgotPage']);
        $app->post('/forgot', [$authController, 'forgot']);
        $app->get('/reset/{token}', [$authController, 'resetPage']);
        $app->post('/reset/{token}', [$authController, 'reset']);
        $app->post('/logout', [$authController, 'logout']);

        /* SHOP-05/06 cart and checkout */
        $app->get('/cart', [$cartController, 'cartPage']);
        $app->post('/cart/add', [$cartController, 'add']);
        $app->post('/cart/update/{id:\d+}', [$cartController, 'update']);
        $app->post('/cart/remove/{id:\d+}', [$cartController, 'remove']);
        $app->get('/checkout', [$cartController, 'checkoutPage']);
        $app->post('/checkout', [$cartController, 'checkout']);

        /* SHOP-07/08/11 customer area */
        $app->group('/account', function (RouteCollectorProxy $group) use ($accountController): void {
            $group->get('', [$accountController, 'profile']);
            $group->get('/', [$accountController, 'profile']);
            $group->post('/profile', [$accountController, 'updateProfile']);
            $group->get('/addresses', [$accountController, 'addresses']);
            $group->post('/addresses', [$accountController, 'addAddress']);
            $group->post('/addresses/{id:\d+}/delete', [$accountController, 'deleteAddress']);
            $group->get('/orders', [$accountController, 'orders']);
            $group->get('/orders/{id:\d+}', [$accountController, 'orderDetail']);
            $group->post('/orders/{id:\d+}/status', [$accountController, 'transition']);
        })->add(new PageAuthMiddleware());

        /* SHOP-04/09/10/12 seller area (also available to administrators) */
        $app->group('/seller', function (RouteCollectorProxy $group) use ($sellerController): void {
            $group->get('', [$sellerController, 'dashboard']);
            $group->get('/', [$sellerController, 'dashboard']);
            $group->get('/products', [$sellerController, 'products']);
            $group->get('/products/new', [$sellerController, 'productNew']);
            $group->post('/products/new', [$sellerController, 'productCreate']);
            $group->get('/products/{id:\d+}/edit', [$sellerController, 'productEdit']);
            $group->post('/products/{id:\d+}/edit', [$sellerController, 'productUpdate']);
            $group->post('/products/{id:\d+}/toggle', [$sellerController, 'productToggle']);
            $group->get('/orders', [$sellerController, 'orders']);
            $group->post('/orders/{id:\d+}/status', [$sellerController, 'orderStatus']);
            $group->get('/inventory', [$sellerController, 'inventory']);
            $group->post('/inventory/{id:\d+}/restock', [$sellerController, 'inventoryRestock']);
            $group->post('/inventory/{id:\d+}/adjust', [$sellerController, 'inventoryAdjust']);
            $group->get('/reports', [$sellerController, 'reports']);
            $group->get('/reports/export/{type}', [$sellerController, 'export']);
            $group->get('/promotions', [$sellerController, 'promotions']);
            $group->post('/promotions', [$sellerController, 'promotionCreate']);
            $group->post('/promotions/{id:\d+}/toggle', [$sellerController, 'promotionToggle']);
        })->add(new PageRoleMiddleware(['seller', 'admin']));

        /* SHOP-10/12 admin area */
        $app->group('/admin', function (RouteCollectorProxy $group) use ($adminController): void {
            $group->get('', [$adminController, 'dashboard']);
            $group->get('/', [$adminController, 'dashboard']);
            $group->get('/users', [$adminController, 'users']);
            $group->post('/users/{id:\d+}/manage', [$adminController, 'userManage']);
            $group->get('/settings', [$adminController, 'settings']);
            $group->post('/settings', [$adminController, 'settingsSave']);
            $group->get('/audit', [$adminController, 'audit']);
        })->add(new PageRoleMiddleware(['admin']));

        /* SHOP-03 review moderation for moderators and admins */
        $app->group('/admin/reviews', function (RouteCollectorProxy $group) use ($adminController): void {
            $group->get('', [$adminController, 'reviews']);
            $group->post('/{id:\d+}/moderate', [$adminController, 'reviewModerate']);
        })->add(new PageRoleMiddleware(['admin', 'moderator']));

        /* SHOP-01..13 JSON API contract */
        $app->group('/api/shop', function (RouteCollectorProxy $group) use ($apiController): void {
            $group->map(['GET', 'POST'], '/accounts', [$apiController, 'accounts']);
            $group->patch('/accounts/{id:\d+}', [$apiController, 'accountsUpdate']);

            $group->map(['GET', 'POST'], '/catalog_search', [$apiController, 'catalogSearch']);
            $group->patch('/catalog_search/{id:\d+}', [$apiController, 'catalogSearchUpdate']);

            $group->map(['GET', 'POST'], '/reviews', [$apiController, 'reviews']);
            $group->patch('/reviews/{id:\d+}', [$apiController, 'reviewUpdate']);

            $group->map(['GET', 'POST'], '/catalog_management', [$apiController, 'catalogManagement']);
            $group->patch('/catalog_management/{id:\d+}', [$apiController, 'catalogManagementUpdate']);

            $group->map(['GET', 'POST'], '/shopping_cart', [$apiController, 'shoppingCart']);
            $group->patch('/shopping_cart/{id:\d+}', [$apiController, 'shoppingCartUpdate']);

            $group->map(['GET', 'POST'], '/checkout', [$apiController, 'checkout']);
            $group->patch('/checkout/{id:\d+}', [$apiController, 'checkoutUpdate']);

            $group->map(['GET', 'POST'], '/order_access', [$apiController, 'orderAccess']);
            $group->patch('/order_access/{id:\d+}', [$apiController, 'orderAccessUpdate']);

            $group->map(['GET', 'POST'], '/order_lifecycle', [$apiController, 'orderLifecycle']);
            $group->patch('/order_lifecycle/{id:\d+}', [$apiController, 'orderLifecycleUpdate']);

            $group->map(['GET', 'POST'], '/inventory', [$apiController, 'inventory']);
            $group->patch('/inventory/{id:\d+}', [$apiController, 'inventoryUpdate']);

            $group->map(['GET', 'POST'], '/seller_and_administrator_operations', [$apiController, 'sellerAdminOperations']);
            $group->patch('/seller_and_administrator_operations/{id:\d+}', [$apiController, 'sellerAdminOperationsUpdate']);

            $group->map(['GET', 'POST'], '/customer_data', [$apiController, 'customerData']);
            $group->patch('/customer_data/{id:\d+}', [$apiController, 'customerDataUpdate']);

            $group->map(['GET', 'POST'], '/reports', [$apiController, 'reports']);
            $group->patch('/reports/{id:\d+}', [$apiController, 'reportsUpdate']);

            $group->map(['GET', 'POST'], '/frontend_api_integration', [$apiController, 'frontendApi']);
            $group->patch('/frontend_api_integration/{id:\d+}', [$apiController, 'frontendApiUpdate']);
        });

        return $app;
    }
}
