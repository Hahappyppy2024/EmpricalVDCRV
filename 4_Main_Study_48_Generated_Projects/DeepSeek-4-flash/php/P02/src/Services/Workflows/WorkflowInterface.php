<?php

declare(strict_types=1);

namespace App\Services\Workflows;

interface WorkflowInterface
{
    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listFor(array $user, array $query): array;

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function show(array $user, int $id): array;

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $user, array $input): array;

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(array $user, int $id, array $input): array;
}
