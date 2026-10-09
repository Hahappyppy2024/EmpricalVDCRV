<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Models\ProductRepository;

final class CatalogSearchController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $filters = $this->filtersFromRequest($request);
        $results = ProductRepository::search($filters);
        return $this->render($response, 'catalog.php', [
            'page_title' => 'Catalog',
            'filters' => $filters,
            'results' => $results,
            'categories' => ProductRepository::categories(),
        ]);
    }

    public function productPage(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($id);
        if (!$product || $product['status'] !== 'published') {
            return $this->render($response, 'error.php', [
                'page_title' => 'Product not found',
                'error_message' => 'The requested product is unavailable.',
            ]);
        }
        $categories = ProductRepository::categories();
        $categoryName = '';
        foreach ($categories as $c) {
            if ((int)$c['id'] === (int)$product['category_id']) {
                $categoryName = (string)$c['name'];
                break;
            }
        }
        $reviews = \Shop\Models\ReviewRepository::forProduct($id, true);
        return $this->render($response, 'product.php', [
            'page_title' => $product['name'],
            'product' => $product,
            'category_name' => $categoryName,
            'reviews' => $reviews,
        ]);
    }

    public function apiGet(Request $request, Response $response): Response
    {
        $filters = $this->filtersFromRequest($request);
        $results = ProductRepository::search($filters);
        return $this->json($response, ['filters' => $filters, 'results' => $results]);
    }

    public function apiPost(Request $request, Response $response): Response
    {
        $filters = $this->filtersFromRequest($request, $this->jsonBody($request));
        $results = ProductRepository::search($filters);
        return $this->json($response, ['filters' => $filters, 'results' => $results]);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $product = ProductRepository::find($id);
        if (!$product) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }
        $results = ProductRepository::search(['q' => (string)$product['sku']]);
        return $this->json($response, ['product' => $product, 'matches' => $results]);
    }

    private function filtersFromRequest(Request $request, array $extra = []): array
    {
        $query = $request->getQueryParams();
        $filters = array_merge($query, $extra);
        return [
            'q' => trim((string)($filters['q'] ?? '')),
            'category' => (string)($filters['category'] ?? ''),
            'min_price_cents' => $filters['min_price_cents'] ?? null,
            'max_price_cents' => $filters['max_price_cents'] ?? null,
            'in_stock' => isset($filters['in_stock']) ? (string)$filters['in_stock'] : '',
        ];
    }
}