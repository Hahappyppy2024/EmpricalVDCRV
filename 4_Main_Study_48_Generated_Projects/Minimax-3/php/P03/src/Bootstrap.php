<?php
declare(strict_types=1);

namespace Shop;

use Slim\Factory\AppFactory;
use Slim\App;
use Shop\Auth\SessionManager;
use Shop\Support\Csrf;
use Shop\Support\FlashBag;
use Shop\Controllers\AccountsController;
use Shop\Controllers\CatalogSearchController;
use Shop\Controllers\ReviewsController;
use Shop\Controllers\CatalogManagementController;
use Shop\Controllers\ShoppingCartController;
use Shop\Controllers\CheckoutController;
use Shop\Controllers\OrderAccessController;
use Shop\Controllers\OrderLifecycleController;
use Shop\Controllers\InventoryController;
use Shop\Controllers\SellerAdminOpsController;
use Shop\Controllers\CustomerDataController;
use Shop\Controllers\ReportsController;
use Shop\Controllers\FrontendApiController;
use Shop\Controllers\HomeController;
use Shop\Models\CartRepository;

final class Bootstrap
{
    public static function app(): App
    {
        Config::load();
        Database::migrate();

        $app = AppFactory::create();
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $app->add(function ($request, $handler) {
            SessionManager::start();
            return $handler->handle($request);
        });

        $app->get('/', [HomeController::class, 'index']);
        $app->get('/login', [AccountsController::class, 'loginForm']);
        $app->post('/login', [AccountsController::class, 'login']);
        $app->get('/register', [AccountsController::class, 'registerForm']);
        $app->post('/register', [AccountsController::class, 'register']);
        $app->post('/logout', [AccountsController::class, 'logout']);
        $app->get('/password/forgot', [AccountsController::class, 'forgotForm']);
        $app->post('/password/forgot', [AccountsController::class, 'forgot']);
        $app->get('/password/reset', [AccountsController::class, 'resetForm']);
        $app->post('/password/reset', [AccountsController::class, 'reset']);
        $app->get('/account', [AccountsController::class, 'account']);
        $app->patch('/account', [AccountsController::class, 'updateAccount']);

        $app->get('/api/shop/accounts', [AccountsController::class, 'apiIndex']);
        $app->post('/api/shop/accounts', [AccountsController::class, 'apiCreate']);
        $app->patch('/api/shop/accounts/{id}', [AccountsController::class, 'apiUpdate']);

        $app->get('/catalog', [CatalogSearchController::class, 'page']);
        $app->get('/api/shop/catalog_search', [CatalogSearchController::class, 'apiGet']);
        $app->post('/api/shop/catalog_search', [CatalogSearchController::class, 'apiPost']);
        $app->patch('/api/shop/catalog_search/{id}', [CatalogSearchController::class, 'apiPatch']);

        $app->get('/products/{id}', [CatalogSearchController::class, 'productPage']);
        $app->get('/api/shop/reviews', [ReviewsController::class, 'apiIndex']);
        $app->post('/api/shop/reviews', [ReviewsController::class, 'apiCreate']);
        $app->patch('/api/shop/reviews/{id}', [ReviewsController::class, 'apiPatch']);
        $app->get('/reviews/moderation', [ReviewsController::class, 'moderationPage']);
        $app->post('/reviews/moderation/{id}', [ReviewsController::class, 'moderate']);

        $app->get('/seller/catalog', [CatalogManagementController::class, 'page']);
        $app->get('/api/shop/catalog_management', [CatalogManagementController::class, 'apiIndex']);
        $app->post('/api/shop/catalog_management', [CatalogManagementController::class, 'apiCreate']);
        $app->patch('/api/shop/catalog_management/{id}', [CatalogManagementController::class, 'apiUpdate']);

        $app->get('/cart', [ShoppingCartController::class, 'page']);
        $app->get('/api/shop/shopping_cart', [ShoppingCartController::class, 'apiIndex']);
        $app->post('/api/shop/shopping_cart', [ShoppingCartController::class, 'apiCreate']);
        $app->patch('/api/shop/shopping_cart/{id}', [ShoppingCartController::class, 'apiUpdate']);
        $app->post('/cart/items/{id}', [ShoppingCartController::class, 'updateItem']);
        $app->post('/cart/items/{id}/delete', [ShoppingCartController::class, 'removeItem']);
        $app->post('/cart/promotion', [ShoppingCartController::class, 'applyPromotion']);

        $app->get('/checkout', [CheckoutController::class, 'page']);
        $app->post('/checkout', [CheckoutController::class, 'place']);
        $app->get('/api/shop/checkout', [CheckoutController::class, 'apiIndex']);
        $app->post('/api/shop/checkout', [CheckoutController::class, 'apiCreate']);
        $app->patch('/api/shop/checkout/{id}', [CheckoutController::class, 'apiPatch']);

        $app->get('/orders', [OrderAccessController::class, 'page']);
        $app->get('/orders/{id}', [OrderAccessController::class, 'detail']);
        $app->get('/api/shop/order_access', [OrderAccessController::class, 'apiIndex']);
        $app->post('/api/shop/order_access', [OrderAccessController::class, 'apiCreate']);
        $app->patch('/api/shop/order_access/{id}', [OrderAccessController::class, 'apiPatch']);

        $app->get('/orders/{id}/lifecycle', [OrderLifecycleController::class, 'page']);
        $app->post('/orders/{id}/lifecycle', [OrderLifecycleController::class, 'transition']);
        $app->get('/api/shop/order_lifecycle', [OrderLifecycleController::class, 'apiIndex']);
        $app->post('/api/shop/order_lifecycle', [OrderLifecycleController::class, 'apiCreate']);
        $app->patch('/api/shop/order_lifecycle/{id}', [OrderLifecycleController::class, 'apiPatch']);

        $app->get('/inventory', [InventoryController::class, 'page']);
        $app->get('/inventory/{id}', [InventoryController::class, 'product']);
        $app->post('/inventory/{id}', [InventoryController::class, 'adjust']);
        $app->get('/api/shop/inventory', [InventoryController::class, 'apiIndex']);
        $app->post('/api/shop/inventory', [InventoryController::class, 'apiCreate']);
        $app->patch('/api/shop/inventory/{id}', [InventoryController::class, 'apiUpdate']);

        $app->get('/admin/operations', [SellerAdminOpsController::class, 'page']);
        $app->post('/admin/operations/promotions', [SellerAdminOpsController::class, 'createPromotion']);
        $app->post('/admin/operations/promotions/{id}/toggle', [SellerAdminOpsController::class, 'togglePromotion']);
        $app->post('/admin/operations/users/{id}/status', [SellerAdminOpsController::class, 'setUserStatus']);
        $app->post('/admin/operations/settings', [SellerAdminOpsController::class, 'updateSetting']);
        $app->get('/api/shop/seller_and_administrator_operations', [SellerAdminOpsController::class, 'apiIndex']);
        $app->post('/api/shop/seller_and_administrator_operations', [SellerAdminOpsController::class, 'apiCreate']);
        $app->patch('/api/shop/seller_and_administrator_operations/{id}', [SellerAdminOpsController::class, 'apiPatch']);

        $app->get('/customer/data', [CustomerDataController::class, 'page']);
        $app->post('/customer/data/preferences', [CustomerDataController::class, 'preferences']);
        $app->post('/customer/data/addresses', [CustomerDataController::class, 'addAddress']);
        $app->post('/customer/data/addresses/{id}/delete', [CustomerDataController::class, 'deleteAddress']);
        $app->get('/api/shop/customer_data', [CustomerDataController::class, 'apiIndex']);
        $app->post('/api/shop/customer_data', [CustomerDataController::class, 'apiCreate']);
        $app->patch('/api/shop/customer_data/{id}', [CustomerDataController::class, 'apiPatch']);

        $app->get('/reports', [ReportsController::class, 'page']);
        $app->get('/reports/export.csv', [ReportsController::class, 'exportCsv']);
        $app->get('/api/shop/reports', [ReportsController::class, 'apiIndex']);
        $app->post('/api/shop/reports', [ReportsController::class, 'apiCreate']);
        $app->patch('/api/shop/reports/{id}', [ReportsController::class, 'apiPatch']);

        $app->get('/frontend/api', [FrontendApiController::class, 'page']);
        $app->get('/api/shop/frontend_api_integration', [FrontendApiController::class, 'apiIndex']);
        $app->post('/api/shop/frontend_api_integration', [FrontendApiController::class, 'apiCreate']);
        $app->patch('/api/shop/frontend_api_integration/{id}', [FrontendApiController::class, 'apiPatch']);

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(function ($request, $exception, bool $display) {
            if ($exception instanceof \Shop\Controllers\HttpJsonException) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode($exception->payload, JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($exception->status);
            }
            if ($exception instanceof \Shop\Controllers\HttpRedirectException) {
                return (new \Slim\Psr7\Response())
                    ->withHeader('Location', $exception->location)
                    ->withStatus($exception->status);
            }
            $response = new \Slim\Psr7\Response();
            $user = SessionManager::user();
            $csrf = Csrf::token();
            $cartCount = $user ? count(CartRepository::items(CartRepository::ensureCart((int)$user['id']))) : 0;
            $html = \Shop\Support\View::render('error.php', [
                'page_title' => 'Error',
                'error_message' => htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'),
                'user' => $user,
                'csrf' => $csrf,
                'flash_html' => FlashBag::render(),
                'cart_count' => $cartCount,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(500);
        });

        return $app;
    }
}