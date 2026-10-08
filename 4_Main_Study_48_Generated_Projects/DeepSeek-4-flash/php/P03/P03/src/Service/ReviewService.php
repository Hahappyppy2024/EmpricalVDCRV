<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\ProductRepository;
use Shop\Repository\ReviewRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-03 Reviews: customers post product reviews, moderators manage content.
 */
final class ReviewService
{
    public function __construct(
        private ReviewRepository $reviews,
        private ProductRepository $products
    ) {
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public function create(array $user, int $productId, array $data): array
    {
        $product = $this->products->byId($productId);
        if ($product === null) {
            throw new DomainException('Product not found.', 404);
        }
        $errors = Validation::required($data, 'text');
        $rating = Validation::int($data['rating'] ?? null, 1, 5);
        if ($rating === null) {
            $errors['rating'] = 'Rating must be an integer between 1 and 5.';
        }
        Validation::throw($errors);

        $title = trim((string) ($data['title'] ?? ''));
        if (mb_strlen(trim((string) $data['text'])) > 2000) {
            throw new ValidationException(['text' => 'Review text must be 2000 characters or fewer.']);
        }

        try {
            $id = $this->reviews->create($productId, (int) $user['id'], $rating, $title, trim((string) $data['text']));
        } catch (\PDOException $e) {
            throw new DomainException('You have already reviewed this product.', 409);
        }
        $review = $this->reviews->byId($id);
        if ($review === null) {
            throw new DomainException('Review could not be created.', 500);
        }
        return $review;
    }

    /**
     * @param array<string,mixed> $moderator
     */
    public function moderate(array $moderator, int $id, string $decision): array
    {
        $status = match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => throw new ValidationException(['decision' => 'Decision must be "approve" or "reject".']),
        };
        $review = $this->reviews->byId($id);
        if ($review === null) {
            throw new DomainException('Review not found.', 404);
        }
        $this->reviews->moderate($id, $status, (int) $moderator['id']);
        return $this->reviews->byId($id) ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pending(): array
    {
        return $this->reviews->pending();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 50): array
    {
        return $this->reviews->recent($limit);
    }
}
