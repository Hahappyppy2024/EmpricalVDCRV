<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\ImageStore;
use Shop\Repository\AuditRepository;
use Shop\Repository\CategoryRepository;
use Shop\Repository\ProductRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-04 Catalog management: sellers create and update products, images,
 * descriptions and prices. Every privileged write is audited.
 */
final class CatalogManagementService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private ImageStore $images,
        private AuditRepository $audit
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(array $user, array $filters = []): array
    {
        $isAdmin = $user['role'] === 'admin';
        return $this->products->search($filters, includeInactive: true, sellerId: $isAdmin ? null : (int) $user['id']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function categories(): array
    {
        return $this->categories->all();
    }

    public function get(array $user, int $id): array
    {
        $product = $this->products->byId($id, true);
        if ($product === null) {
            throw new DomainException('Product not found.', 404);
        }
        $this->assertOwnerOrAdmin($user, $product);
        return $product;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $upload
     */
    public function create(array $user, array $data, ?array $upload): array
    {
        $errors = Validation::required($data, 'name', 'category_id');
        $price = Validation::float($data['price'] ?? null, 0, 1000000);
        $stock = Validation::int($data['stock'] ?? null, 0, 1000000);
        if ($price === null) {
            $errors['price'] = 'Price must be a number between 0 and 1000000.';
        }
        if ($stock === null) {
            $errors['stock'] = 'Stock must be an integer between 0 and 1000000.';
        }
        Validation::throw($errors);

        $slug = $this->uniqueSlug((string) $data['name']);
        $image = $this->handleImage($upload);

        $id = $this->products->create([
            'seller_id' => (int) $user['id'],
            'category_id' => (int) $data['category_id'],
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')),
            'price' => $price,
            'stock' => $stock,
            'image' => $image,
            'active' => !empty($data['active']),
            'featured' => !empty($data['featured']),
        ]);
        $this->audit->log((int) $user['id'], 'catalog.product.create', 'Product', $id, "Created product '{$data['name']}'");
        return $this->products->byId($id, true) ?? [];
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $upload
     */
    public function update(array $user, int $id, array $data, ?array $upload): array
    {
        $product = $this->get($user, $id);
        $errors = Validation::required($data, 'name', 'category_id');
        $price = Validation::float($data['price'] ?? null, 0, 1000000);
        $stock = Validation::int($data['stock'] ?? null, 0, 1000000);
        if ($price === null) {
            $errors['price'] = 'Price must be a number between 0 and 1000000.';
        }
        if ($stock === null) {
            $errors['stock'] = 'Stock must be an integer between 0 and 1000000.';
        }
        Validation::throw($errors);

        $slug = $this->uniqueSlug((string) $data['name'], $id);
        $image = $this->handleImage($upload); // null keeps the previous image

        $this->products->update($id, [
            'category_id' => (int) $data['category_id'],
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')),
            'price' => $price,
            'stock' => $stock,
            'image' => $image,
            'active' => !empty($data['active']),
            'featured' => !empty($data['featured']),
        ]);
        $this->audit->log((int) $user['id'], 'catalog.product.update', 'Product', $id, "Updated product '{$data['name']}'");
        return $this->products->byId($id, true) ?? [];
    }

    /**
     * @param array<string,mixed> $user
     */
    public function toggleActive(array $user, int $id): array
    {
        $product = $this->get($user, $id);
        $this->products->setActive($id, (int) $product['active'] === 1 ? 0 : 1);
        $this->audit->log((int) $user['id'], 'catalog.product.toggle', 'Product', $id, 'Toggled product visibility');
        return $this->products->byId($id, true) ?? [];
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $product
     */
    private function assertOwnerOrAdmin(array $user, array $product): void
    {
        if ($user['role'] === 'admin') {
            return;
        }
        if ($user['role'] !== 'seller' || (int) $user['id'] !== (int) $product['seller_id']) {
            throw new DomainException('You are not allowed to manage this product.', 403);
        }
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Validation::slugify($name);
        $slug = $base;
        $i = 2;
        while ($this->products->slugTaken($slug, $exceptId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /**
     * @param array<string,mixed>|null $upload
     */
    private function handleImage(?array $upload): ?string
    {
        if ($upload === null || !isset($upload['image']) || ($upload['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $this->images->save($upload['image']);
    }
}
