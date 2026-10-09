<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\ProductRepository;
use Shop\Models\AuditRepository;
use Shop\Models\InventoryRepository;
use Shop\Support\Storage;
use Shop\Support\Validator;

final class CatalogManagementController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $products = ProductRepository::listForSeller((int)$user['id']);
        return $this->render($response, 'catalog_management.php', [
            'page_title' => 'Catalog management',
            'products' => $products,
            'categories' => ProductRepository::categories(),
        ]);
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        return $this->json($response, ['products' => ProductRepository::listForSeller((int)$user['id'])]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $data = $this->jsonBody($request);
        $sku = strtoupper(trim((string)($data['sku'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $priceCents = (int)($data['price_cents'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0) ?: null;
        $status = in_array($data['status'] ?? null, ['draft', 'published', 'archived', 'inactive'], true) ? (string)$data['status'] : 'published';
        if (!Validator::text($sku, 2, 32) || !Validator::text($name, 2, 120) || $priceCents < 0 || ProductRepository::bySku($sku)) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        $id = ProductRepository::create([
            'sku' => $sku,
            'name' => $name,
            'description' => $description,
            'price_cents' => $priceCents,
            'category_id' => $categoryId,
            'seller_id' => (int)$user['id'],
            'status' => $status,
        ]);
        InventoryRepository::adjust($id, (int)($data['initial_stock'] ?? 0), 'initial', (int)$user['id']);
        AuditRepository::log((int)$user['id'], 'product_create', 'product', (string)$id, ['sku' => $sku]);
        return $this->json($response, ['product' => ProductRepository::find($id)], 201);
    }

    public function apiUpdate(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'seller');
        $id = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($id);
        if (!$product) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }
        if ($user['role'] !== 'admin' && (int)$product['seller_id'] !== (int)$user['id']) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        $data = $this->jsonBody($request);
        $update = [];
        if (isset($data['name'])) {
            $update['name'] = trim((string)$data['name']);
        }
        if (isset($data['description'])) {
            $update['description'] = trim((string)$data['description']);
        }
        if (isset($data['price_cents'])) {
            $update['price_cents'] = max(0, (int)$data['price_cents']);
        }
        if (isset($data['category_id'])) {
            $update['category_id'] = (int)$data['category_id'] ?: null;
        }
        if (isset($data['status'])) {
            $update['status'] = in_array($data['status'], ['draft', 'published', 'archived', 'inactive'], true) ? (string)$data['status'] : 'draft';
        }
        ProductRepository::update($id, $update);
        AuditRepository::log((int)$user['id'], 'product_update', 'product', (string)$id, $update);
        return $this->json($response, ['product' => ProductRepository::find($id)]);
    }
}