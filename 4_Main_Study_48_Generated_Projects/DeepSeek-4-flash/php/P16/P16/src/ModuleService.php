<?php

declare(strict_types=1);

namespace App;

interface ModuleService
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function list(array $filters, array $user): array;

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function item(int $id, array $user): array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function create(array $data, array $user): array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, array $user): array;

    /**
     * @param array<string, mixed> $user
     */
    public function delete(int $id, array $user): void;
}
