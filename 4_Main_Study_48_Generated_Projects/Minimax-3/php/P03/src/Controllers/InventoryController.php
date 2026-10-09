<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\ProductRepository;
use Shop\Models\InventoryRepository;
use Shop\Models\AuditRepository;

final class InventoryController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $rows = \Shop\Models\ReportRepository::inventoryReport();
        $filtered = [];
        foreach ($rows as $row) {
            $product = ProductRepository::find((int)$row['id']);
            if ($product && ((int)$product['seller_id'] === (int)$user['id'] || $user['role'] === 'admin')) {
                $filtered[] = $row;
            }
        }
        return $this->render($response, 'inventory.php', [
            'page_title' => 'Inventory',
            'rows' => $filtered,
        ]);
    }

    public function product(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'seller');
        $id = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($id);
        if (!$product || ((int)$product['seller_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            return $this->render($response, 'error.php', [
                'page_title' => 'Forbidden',
                'error_message' => 'You cannot manage this product.',
            ]);
        }
        return $this->render($response, 'inventory_product.php', [
            'page_title' => 'Inventory: ' . ($product['name'] ?? ''),
            'product' => $product,
            'inventory' => InventoryRepository::forProduct($id) ?: ['quantity' => 0, 'restock_threshold' => 0],
            'events' => InventoryRepository::eventsFor($id),
        ]);
    }

    public function adjust(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'seller');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/inventory');
        }
        $id = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($id);
        if (!$product || ((int)$product['seller_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            SessionManager::flash('error', 'You cannot manage this product.');
            return $this->redirect($response, '/inventory');
        }
        $delta = (int)$this->input($request, 'delta');
        $reason = trim((string)$this->input($request, 'reason', 'manual'));
        $threshold = (int)$this->input($request, 'restock_threshold');
        InventoryRepository::adjust($id, $delta, $reason, (int)$user['id']);
        if ($threshold > 0) {
            InventoryRepository::setRestockThreshold($id, $threshold);
        }
        AuditRepository::log((int)$user['id'], 'inventory_adjust', 'product', (string)$id, ['delta' => $delta, 'reason' => $reason]);
        SessionManager::flash('success', 'Inventory updated.');
        return $this->redirect($response, '/inventory/' . $id);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $rows = [];
        foreach (\Shop\Models\ReportRepository::inventoryReport() as $row) {
            $product = ProductRepository::find((int)$row['id']);
            if ($product && ((int)$product['seller_id'] === (int)$user['id'] || $user['role'] === 'admin')) {
                $rows[] = $row;
            }
        }
        return $this->json($response, ['inventory' => $rows]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $data = $this->jsonBody($request);
        $productId = (int)($data['product_id'] ?? 0);
        $delta = (int)($data['delta'] ?? 0);
        if ($productId <= 0 || $delta === 0) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        $product = ProductRepository::find($productId);
        if (!$product || ((int)$product['seller_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        InventoryRepository::adjust($productId, $delta, (string)($data['reason'] ?? 'api'), (int)$user['id']);
        AuditRepository::log((int)$user['id'], 'inventory_adjust_api', 'product', (string)$productId, ['delta' => $delta]);
        return $this->json($response, ['inventory' => InventoryRepository::forProduct($productId)]);
    }

    public function apiUpdate(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'seller');
        $productId = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($productId);
        if (!$product || ((int)$product['seller_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        $data = $this->jsonBody($request);
        $threshold = (int)($data['restock_threshold'] ?? 0);
        InventoryRepository::setRestockThreshold($productId, max(0, $threshold));
        AuditRepository::log((int)$user['id'], 'inventory_threshold', 'product', (string)$productId, ['threshold' => $threshold]);
        return $this->json($response, ['inventory' => InventoryRepository::forProduct($productId)]);
    }
}