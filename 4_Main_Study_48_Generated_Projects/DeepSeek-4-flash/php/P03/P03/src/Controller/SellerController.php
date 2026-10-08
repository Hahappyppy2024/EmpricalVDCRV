<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\Service\AdminService;
use Shop\Service\CatalogManagementService;
use Shop\Service\InventoryService;
use Shop\Service\OrderService;
use Shop\Service\ReportService;
use Shop\View;

/**
 * SHOP-04, SHOP-09, SHOP-10, SHOP-12 seller browser workflows.
 * The same routes are available to administrators.
 */
final class SellerController extends Controller
{
    public function __construct(
        private View $view,
        private CatalogManagementService $catalog,
        private InventoryService $inventory,
        private OrderService $orders,
        private AdminService $admin,
        private ReportService $reports
    ) {
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/dashboard', [
            'stats' => $this->admin->dashboardStats($user),
        ]));
    }

    public function products(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/products', [
            'products' => $this->catalog->list($user, $this->query($request)),
            'categories' => $this->catalog->categories(),
            'filters' => $this->query($request),
        ]));
    }

    public function productNew(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/product_form', [
            'product' => null,
            'categories' => $this->catalog->categories(),
        ]));
    }

    public function productCreate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        try {
            $product = $this->catalog->create($user, $this->body($request), $_FILES);
            return $this->redirect($response, '/seller/products/' . $product['id'] . '/edit' . flash_query(null, 'Product created.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/products/new' . flash_query($e->getMessage()));
        }
    }

    public function productEdit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/product_form', [
            'product' => $this->catalog->get($user, $this->param($args, 'id')),
            'categories' => $this->catalog->categories(),
        ]));
    }

    public function productUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        try {
            $this->catalog->update($user, $this->param($args, 'id'), $this->body($request), $_FILES);
            return $this->redirect($response, '/seller/products/' . $this->param($args, 'id') . '/edit' . flash_query(null, 'Product updated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/products/' . $this->param($args, 'id') . '/edit' . flash_query($e->getMessage()));
        }
    }

    public function productToggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        try {
            $this->catalog->toggleActive($user, $this->param($args, 'id'));
        } catch (\Throwable $e) {
            // stable redirect
        }
        return $this->redirect($response, '/seller/products');
    }

    public function orders(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/orders', [
            'orders' => $this->orders->list($user, $this->query($request)),
            'filters' => $this->query($request),
            'statuses' => \Shop\Service\OrderService::STATUSES,
        ]));
    }

    public function orderStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $data = $this->body($request);
        try {
            $this->orders->transition($user, $this->param($args, 'id'), (string) ($data['status'] ?? ''), (string) ($data['comment'] ?? ''));
            return $this->redirect($response, '/seller/orders' . flash_query(null, 'Order status updated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/orders' . flash_query($e->getMessage()));
        }
    }

    public function inventory(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/inventory', [
            'products' => $this->inventory->list($user, $this->query($request)),
            'movements' => $this->inventory->movements($user),
        ]));
    }

    public function inventoryRestock(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $data = $this->body($request);
        try {
            $this->inventory->restock($user, $this->param($args, 'id'), (int) ($data['quantity'] ?? 0));
            return $this->redirect($response, '/seller/inventory' . flash_query(null, 'Stock restocked.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/inventory' . flash_query($e->getMessage()));
        }
    }

    public function inventoryAdjust(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $data = $this->body($request);
        try {
            $this->inventory->adjust($user, $this->param($args, 'id'), (int) ($data['delta'] ?? 0), (string) ($data['reason'] ?? ''));
            return $this->redirect($response, '/seller/inventory' . flash_query(null, 'Stock adjusted.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/inventory' . flash_query($e->getMessage()));
        }
    }

    public function reports(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        $type = (string) ($this->query($request)['type'] ?? 'sales');
        return $this->html($response, $this->view->render('seller/reports', [
            'report' => $this->reports->generate($user, $type, $this->query($request)),
            'type' => $type,
            'types' => \Shop\Service\ReportService::TYPES,
        ]));
    }

    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['seller', 'admin']);
        $type = (string) ($args['type'] ?? 'sales');
        $export = $this->reports->export($type);
        return $this->csv($response, $export['filename'], $export['csv']);
    }

    public function promotions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['seller', 'admin']);
        return $this->html($response, $this->view->render('seller/promotions', [
            'promotions' => $this->admin->promotions(),
        ]));
    }

    public function promotionCreate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        try {
            $this->admin->createPromotion($user, $this->body($request));
            return $this->redirect($response, '/seller/promotions' . flash_query(null, 'Promotion created.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/seller/promotions' . flash_query($e->getMessage()));
        }
    }

    public function promotionToggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['seller', 'admin']);
        try {
            $this->admin->togglePromotion($user, $this->param($args, 'id'));
        } catch (\Throwable $e) {
            // stable redirect
        }
        return $this->redirect($response, '/seller/promotions');
    }
}
