<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\CategoryRepository;
use Shop\Repository\ProductRepository;
use Shop\Repository\ReviewRepository;
use Shop\Repository\SearchRepository;

/**
 * SHOP-02 Catalog search: browse products by keyword, category, price, availability.
 */
final class CatalogService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private ReviewRepository $reviews,
        private SearchRepository $searches
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters): array
    {
        $clean = [];
        foreach (['keyword', 'category_id', 'min_price', 'max_price', 'sort'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $clean[$key] = $filters[$key];
            }
        }
        if (!empty($filters['in_stock'])) {
            $clean['in_stock'] = 1;
        }
        if (!empty($filters['featured'])) {
            $clean['featured'] = 1;
        }
        return $this->products->search($clean);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function categories(): array
    {
        return $this->categories->all();
    }

    public function byId(int $id): ?array
    {
        return $this->products->byId($id);
    }

    public function bySlug(string $slug): ?array
    {
        return $this->products->bySlug($slug);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function featured(): array
    {
        return $this->products->featured();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function productReviews(int $productId): array
    {
        return $this->reviews->byProduct($productId, true);
    }

    /**
     * @return array{count:int,average:float}
     */
    public function reviewSummary(int $productId): array
    {
        return $this->reviews->summary($productId);
    }

    /**
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function savedSearches(array $user): array
    {
        return $this->searches->forUser((int) $user['id']);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $filters
     */
    public function saveSearch(array $user, string $name, string $query, array $filters): array
    {
        if (trim($name) === '') {
            throw new DomainException('A search name is required to save the search.', 422);
        }
        $id = $this->searches->create((int) $user['id'], trim($name), trim($query), $filters);
        $saved = $this->searches->byId($id);
        if ($saved === null) {
            throw new DomainException('Search could not be saved.', 500);
        }
        return $saved;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $filters
     */
    public function updateSearch(array $user, int $id, string $name, string $query, array $filters): array
    {
        $saved = $this->searches->byIdForUser($id, (int) $user['id']);
        if ($saved === null) {
            throw new DomainException('Saved search not found.', 404);
        }
        if (trim($name) === '') {
            throw new DomainException('A search name is required.', 422);
        }
        $this->searches->update($id, trim($name), trim($query), $filters);
        return $this->searches->byId($id) ?? [];
    }
}
