<?php

declare(strict_types=1);

namespace Shop\Repository;

final class CategoryRepository extends Repository
{
    public function all(): array
    {
        return $this->rows('SELECT * FROM categories ORDER BY id');
    }

    public function byId(int $id): ?array
    {
        return $this->row('SELECT * FROM categories WHERE id = ?', [$id]);
    }
}
